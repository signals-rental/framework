<?php

namespace App\Services\Shortages;

use App\Enums\ShortageDispatchPolicy;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Store;
use App\ValueObjects\DispatchGateResult;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use Illuminate\Validation\ValidationException;

/**
 * The dispatch shortage gate (shortage-resolution-sub-hires.md §7.4;
 * opportunity-lifecycle.md §7.1/§7.4).
 *
 * The dispatch-time counterpart of {@see ShortageConfirmationGate}: even an order
 * that was knowingly confirmed short re-checks each line before stock leaves the
 * warehouse, governed by the store's {@see ShortageDispatchPolicy} (the
 * `shortage_dispatch_policy` column resolved via {@see Store::dispatchPolicy()}).
 *
 *  - Block        — throws a 422 (`code: dispatch_block`) listing the short lines;
 *    nothing is dispatched.
 *  - WarnPartial  — proceeds; the short line is held back and the held-item
 *    metadata is surfaced on the response (the action overlays it).
 *  - AllowPartial — proceeds silently.
 *
 * The decision is the store policy applied to the shortages the existing
 * {@see ShortageDetector} computes — never a hardcoded matrix and never a new
 * detection path. It is invoked from the dispatch actions (DispatchAsset,
 * DispatchBulkQuantity, QuickBookOut) BEFORE the Verbs event fires, inside the
 * action's atomic transaction, so a Block leaves nothing persisted.
 */
class DispatchShortageGate
{
    /** The machine-readable denial code surfaced when the gate blocks dispatch. */
    public const string CODE = 'dispatch_block';

    public function __construct(
        private readonly ShortageDetector $detector,
        private readonly ShortageEventRecorder $events,
    ) {}

    /**
     * Enforce the gate for a single line item's dispatch. On a Block policy with an
     * unresolved shortage on the line, throws a 422; otherwise returns the result
     * (which carries any held-item metadata for a WarnPartial outcome).
     *
     * @throws ValidationException when the store policy blocks dispatch of a short line
     */
    public function enforceForItem(OpportunityItem $item): DispatchGateResult
    {
        return $this->enforceForItems([$item]);
    }

    /**
     * Evaluate the gate across an opportunity's lines WITHOUT side effects — no
     * `shortage.detected` telemetry, no throw — and return the decision. The
     * read-only counterpart of {@see enforceForItems()}, mirroring
     * {@see ShortageConfirmationGate::evaluate()}.
     *
     * Powers the `available_actions` dispatch precheck (via
     * {@see App\Guards\Opportunities\Rules\DispatchShortageRule}): it reports
     * whether dispatching the order WOULD be blocked by the store's
     * {@see ShortageDispatchPolicy}, without booking anything out. Write-time
     * enforcement stays in {@see enforceForItem()} / {@see enforceForItems()},
     * which operate per-line and surface held-item metadata.
     */
    public function evaluateForOpportunity(Opportunity $opportunity): DispatchGateResult
    {
        $opportunity->loadMissing('items');

        $shortages = $this->detectForItems($opportunity->items);
        $policy = $opportunity->store?->dispatchPolicy() ?? ShortageDispatchPolicy::default();

        return new DispatchGateResult($policy, $shortages);
    }

    /**
     * Enforce the gate across several line items in one dispatch batch (the
     * quick_book_out path). All items share the opportunity's store policy. Detects
     * the residual shortage on each distinct line once, then applies the policy to
     * the aggregate.
     *
     * @param  iterable<OpportunityItem>  $items
     *
     * @throws ValidationException when the store policy blocks dispatch of any short line
     */
    public function enforceForItems(iterable $items): DispatchGateResult
    {
        // Materialise once: $items is consumed twice below (detect, then policy),
        // so a single-pass Generator would be exhausted by the first call and the
        // second would silently see nothing (wrong policy fallback).
        $items = is_array($items) ? $items : iterator_to_array($items);

        $shortages = $this->detectForItems($items);
        $policy = $this->resolvePolicy($items);

        $result = new DispatchGateResult($policy, $shortages);

        if ($result->blocks()) {
            throw ValidationException::withMessages([
                'code' => [self::CODE],
                'shortages' => [$this->blockMessage($shortages)],
            ]);
        }

        // Emit shortage.detected telemetry only when the gate does NOT block. The
        // caller runs this inside its DB::transaction (commitVerbs); emitting before
        // the throw would write the telemetry rows then roll them straight back.
        if ($result->isShort()) {
            $this->events->detected($shortages);
        }

        return $result;
    }

    /**
     * Detect the residual shortage for each DISTINCT line item in the batch (a line
     * is only checked once even if several of its assets are dispatched together).
     *
     * @param  iterable<OpportunityItem>  $items
     */
    private function detectForItems(iterable $items): ShortageCollection
    {
        $shortages = new ShortageCollection;
        $seen = [];

        foreach ($items as $item) {
            if (isset($seen[$item->id])) {
                continue;
            }
            $seen[$item->id] = true;

            $shortage = $this->detector->forItem($item);

            if ($shortage instanceof Shortage && $shortage->isUnresolved()) {
                $shortages->push($shortage);
            }
        }

        return $shortages->values();
    }

    /**
     * Resolve the store dispatch policy from the first item's opportunity (every
     * item in a batch belongs to the same opportunity, hence the same store).
     * Falls back to the policy default when there is no store.
     *
     * @param  iterable<OpportunityItem>  $items
     */
    private function resolvePolicy(iterable $items): ShortageDispatchPolicy
    {
        foreach ($items as $item) {
            return $item->opportunity?->store?->dispatchPolicy()
                ?? ShortageDispatchPolicy::default();
        }

        return ShortageDispatchPolicy::default();
    }

    private function blockMessage(ShortageCollection $shortages): string
    {
        $count = $shortages->count();

        return "Dispatch is blocked: {$count} line(s) are short. Resolve the shortage or change the store dispatch policy before booking out.";
    }
}

<?php

namespace App\Services\Opportunities;

use App\Enums\AssetAssignmentStatus;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use Closure;
use Illuminate\Support\Facades\Gate;

/**
 * Builds the dry-run `{key, label, allowed, reason, code}` verdict for one
 * opportunity action, shared verbatim by the API `available_actions` endpoint and
 * the Livewire Show page's action menu.
 *
 * The verdict combines a generic STATE precondition (the Verbs `validate()`
 * invariant the guard pipeline does not run) with the non-throwing guard-pipeline
 * {@see GuardPipeline::check()} and a direct permission probe, so the UI and API
 * agree on which transitions are reachable without ever firing an event.
 */
class OpportunityActionDescriber
{
    /**
     * Describe one available action: a permission probe, a generic state
     * precondition, then the non-throwing guard-pipeline dry-run for transitions
     * that route through it. The pipeline check only runs when the state
     * precondition and the permission pass, so its shortage/FX-lock prechecks
     * reflect a genuinely reachable transition.
     *
     * @param  Closure(): (array{0: string, 1: string}|null)|null  $statePrecondition  Returns a `[message, code]` denial or null to pass.
     * @return array{key: string, label: string, allowed: bool, reason: string|null, code: string|null}
     */
    public function describe(
        Opportunity $opportunity,
        string $key,
        string $label,
        string $permission,
        ?string $transition,
        ?Closure $statePrecondition = null,
    ): array {
        // 1. Permission probe (no 403 — a dry-run verdict).
        if (! Gate::allows($permission)) {
            return $this->verdict($key, $label, false, 'You do not have permission to perform this action.', 'permission_denied');
        }

        // 2. Generic state precondition (mirrors the event's Verbs validate()).
        if ($statePrecondition !== null) {
            $stateDenial = $statePrecondition();

            if ($stateDenial !== null) {
                return $this->verdict($key, $label, false, $stateDenial[0], $stateDenial[1]);
            }
        }

        // 3. Guard-pipeline dry-run (business rules incl. the shortage gate + FX/tax
        //    lock) for transitions that route through it.
        if ($transition !== null) {
            $result = app(GuardPipeline::class)->check(new TransitionContext(
                transition: $transition,
                opportunity: $opportunity,
                permission: $permission,
            ));

            if ($result->denied()) {
                return $this->verdict($key, $label, false, $result->firstError(), $result->code);
            }
        }

        return $this->verdict($key, $label, true, null, null);
    }

    /**
     * @return array{key: string, label: string, allowed: bool, reason: string|null, code: string|null}
     */
    public function verdict(string $key, string $label, bool $allowed, ?string $reason, ?string $code): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'allowed' => $allowed,
            'reason' => $reason,
            'code' => $code,
        ];
    }

    /**
     * The revert-to-quotation state precondition: must be an open Order with no
     * dispatch history. Returns a `[message, code]` denial or null to pass.
     *
     * @return array{0: string, 1: string}|null
     */
    public function revertToQuotationPrecondition(Opportunity $opportunity, bool $isOrder, bool $isClosed): ?array
    {
        if (! $isOrder || $isClosed) {
            return ['Only an open order can be reverted to a quotation.', 'invalid_state'];
        }

        $dispatched = OpportunityItemAsset::query()
            ->whereIn('opportunity_item_id', $opportunity->allItems()->select('opportunity_items.id'))
            ->where('status', '>=', AssetAssignmentStatus::Dispatched->value)
            ->exists();

        $bulkDispatched = $opportunity->allItems()
            ->where('dispatched_quantity', '>', 0)
            ->exists();

        return $dispatched || $bulkDispatched
            ? ['An order with dispatched assets cannot be reverted to a quotation.', 'dispatched']
            : null;
    }
}

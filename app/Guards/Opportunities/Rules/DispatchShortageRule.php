<?php

namespace App\Guards\Opportunities\Rules;

use App\Enums\ShortageDispatchPolicy;
use App\Guards\Opportunities\Contracts\TransitionRule;
use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\TransitionContext;
use App\Services\Shortages\DispatchShortageGate;

/**
 * Surfaces the dispatch shortage policy in the `available_actions` precheck
 * (shortage-resolution-sub-hires.md §7.4; opportunity-lifecycle.md §12.2).
 *
 * Registered for the `opportunity.dispatch` transition so the guard pipeline's
 * {@see GuardPipeline::check()} dry-run reports whether booking the order out
 * WOULD be blocked by the store's {@see ShortageDispatchPolicy} (Block) — letting
 * the Show-page toolbar reflect a blocked-short order before any write.
 *
 * WRITE-TIME ENFORCEMENT IS DELIBERATELY NOT MOVED HERE. The actual dispatch
 * actions ({@see App\Actions\Opportunities\DispatchAsset},
 * {@see App\Actions\Opportunities\DispatchBulkQuantity},
 * {@see App\Actions\Opportunities\QuickBookOut}) keep calling
 * {@see DispatchShortageGate::enforceForItem()} / enforceForItems() directly,
 * because dispatch is a PER-LINE / per-asset-batch operation (not a whole-
 * opportunity transition): the per-item gate enforces the policy line-by-line,
 * emits `shortage.detected` telemetry, and surfaces held-item metadata
 * (`gateResult`) the controller overlays on a WarnPartial response — none of
 * which an opportunity-level pipeline run could preserve. This rule therefore
 * exists PURELY for the dry-run precheck: its {@see evaluate()} is an allow
 * (write-time enforcement is the per-item gate, run by the actions), and only
 * {@see precheck()} reports the Block verdict, via the gate's side-effect-free
 * {@see DispatchShortageGate::evaluateForOpportunity()}.
 */
class DispatchShortageRule implements TransitionRule
{
    /** The transition this rule guards. */
    public const string TRANSITION = 'opportunity.dispatch';

    /**
     * The machine-readable denial code surfaced when the store dispatch policy
     * would block booking out a short order. The UI branches on it to render a
     * "Resolve shortages" affordance.
     */
    public const string CODE = DispatchShortageGate::CODE;

    public function __construct(
        private readonly DispatchShortageGate $gate,
    ) {}

    public function key(): string
    {
        return 'dispatch_shortage';
    }

    public function appliesTo(TransitionContext $context): bool
    {
        return $context->isTransition(self::TRANSITION);
    }

    /**
     * Write-time enforcement lives in the dispatch actions' per-line
     * {@see DispatchShortageGate::enforceForItem()} calls (see the class docblock),
     * so the pipeline's whole-opportunity run is intentionally a no-op allow here.
     */
    public function evaluate(TransitionContext $context): GuardResult
    {
        return GuardResult::allow();
    }

    /**
     * Dry-run: consult the gate's pure
     * {@see DispatchShortageGate::evaluateForOpportunity()} — NO telemetry and NO
     * throw — so the `available_actions` endpoint can report whether dispatching
     * the order WOULD be blocked by the store's {@see ShortageDispatchPolicy}. A
     * Block decision becomes a {@see GuardResult::deny()} carrying {@see CODE};
     * WarnPartial / AllowPartial pass.
     */
    public function precheck(TransitionContext $context): GuardResult
    {
        $result = $this->gate->evaluateForOpportunity($context->opportunity);

        if ($result->blocks()) {
            return GuardResult::deny('business_rules', [
                'shortages' => ['This order has unresolved shortages; the store dispatch policy blocks booking it out until they are resolved.'],
            ], self::CODE);
        }

        return GuardResult::allow();
    }
}

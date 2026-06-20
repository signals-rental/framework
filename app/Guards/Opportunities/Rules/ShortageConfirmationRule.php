<?php

namespace App\Guards\Opportunities\Rules;

use App\Guards\Opportunities\Contracts\TransitionRule;
use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\TransitionContext;
use App\Services\Shortages\ShortageAutoResolver;
use App\Services\Shortages\ShortageConfirmationGate;

/**
 * The first concrete config-driven business rule (opportunity-lifecycle.md §12.2;
 * shortage-resolution-sub-hires.md §7).
 *
 * Wraps the existing {@see ShortageConfirmationGate} (and the prior
 * {@see ShortageAutoResolver} pass) as a registered {@see TransitionRule} so the
 * guard pipeline runs it as the business-rules stage of the
 * `opportunity.convert_to_order` transition — replacing the inline calls the
 * ConvertToOrder action used to make, with IDENTICAL behaviour:
 *
 *   1. run auto-resolution first (no-op unless the store enables it) so the gate
 *      sees only the RESIDUAL shortage;
 *   2. enforce the store {@see App\Enums\ShortagePolicy} (relaxed by the actor's
 *      `shortages.ignore` permission): Block → throws a 422; Warn → records an
 *      acknowledgement and proceeds; Allow → proceeds silently.
 *
 * The decision is the store's policy + the actor's permission — never a hardcoded
 * matrix. The rule throws the gate's own ValidationException on a Block (so the
 * 422 still lists the shortages), hence it only ever returns
 * {@see GuardResult::allow()} here.
 */
class ShortageConfirmationRule implements TransitionRule
{
    /** The transition this rule guards. */
    public const string TRANSITION = 'opportunity.convert_to_order';

    /**
     * The machine-readable denial code surfaced when the confirmation gate blocks
     * the transition. The UI branches on it to render a "Resolve shortages" path.
     */
    public const string CODE = 'shortage_block';

    public function __construct(
        private readonly ShortageAutoResolver $autoResolver,
        private readonly ShortageConfirmationGate $gate,
    ) {}

    public function key(): string
    {
        return 'shortage_confirmation';
    }

    public function appliesTo(TransitionContext $context): bool
    {
        return $context->isTransition(self::TRANSITION);
    }

    public function evaluate(TransitionContext $context): GuardResult
    {
        // §7.5 — auto-resolve first so the gate evaluates only the residual
        // shortage (a no-op unless the store enables auto-resolution).
        $this->autoResolver->resolve($context->opportunity);

        // Throws ValidationException (→ 422) on a Block; records an acknowledgement
        // and returns on a Warn; returns silently on Allow.
        $this->gate->enforceForConfirmation($context->opportunity, $context->notes);

        return GuardResult::allow();
    }

    /**
     * Dry-run: consult the gate's pure {@see ShortageConfirmationGate::evaluate()}
     * — NO auto-resolution and NO acknowledgement recorded — so the
     * `available_actions` endpoint can report whether converting to an order
     * WOULD be blocked by the store's shortage policy. A Block decision becomes a
     * {@see GuardResult::deny()} carrying the {@see CODE} reason; Warn/Allow pass.
     */
    public function precheck(TransitionContext $context): GuardResult
    {
        $result = $this->gate->evaluate($context->opportunity);

        if ($result->blocks()) {
            return GuardResult::deny('business_rules', [
                'shortages' => ['This order has unresolved shortages; resolve them or obtain the permission to proceed.'],
            ], self::CODE);
        }

        return GuardResult::allow();
    }
}

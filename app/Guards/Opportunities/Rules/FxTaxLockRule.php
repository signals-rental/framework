<?php

namespace App\Guards\Opportunities\Rules;

use App\Guards\Opportunities\Contracts\TransitionRule;
use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\TransitionContext;

/**
 * FX/tax-lock enforcement business rule (multi-currency-tax-engine.md §4.3/§7.2;
 * opportunity-lifecycle.md §12.2).
 *
 * Once an order is confirmed, {@see App\Verbs\Events\Opportunities\OpportunityConvertedToOrder}
 * freezes `exchange_rate_locked` / `tax_locked` so the committed exchange rate and
 * tax figures can never silently re-derive. This rule is the guard-layer
 * complement: it rejects any edit transition whose {@see TransitionContext}
 * declares it would change the rate or tax (`changes_rate` / `changes_tax`) while
 * the opportunity is locked. Once the locks are released
 * ({@see App\Verbs\Events\Opportunities\OpportunityLocksReleased} via the
 * UnlockOpportunity action), the same edits are allowed again.
 *
 * It is matched GENERICALLY by change flags — not by a named status — so any
 * future rate/tax-affecting transition is covered simply by setting the flag on
 * its context (Ben's locked steer).
 */
class FxTaxLockRule implements TransitionRule
{
    /**
     * The machine-readable denial code. The UI branches on it to render an
     * "Unlock rates" CTA (the privileged UnlockOpportunity path).
     */
    public const string CODE = 'fx_tax_locked';

    public function key(): string
    {
        return 'fx_tax_lock';
    }

    public function appliesTo(TransitionContext $context): bool
    {
        return $context->changes('changes_rate', false) === true
            || $context->changes('changes_tax', false) === true;
    }

    public function evaluate(TransitionContext $context): GuardResult
    {
        $opportunity = $context->opportunity;

        if ($context->changes('changes_rate', false) === true && $opportunity->exchange_rate_locked) {
            return GuardResult::deny('business_rules', [
                'exchange_rate' => ['The exchange rate is locked on a confirmed order; release the locks before changing rates.'],
            ], self::CODE);
        }

        if ($context->changes('changes_tax', false) === true && $opportunity->tax_locked) {
            return GuardResult::deny('business_rules', [
                'tax' => ['The tax figures are locked on a confirmed order; release the locks before changing tax.'],
            ], self::CODE);
        }

        return GuardResult::allow();
    }

    /**
     * The rule is already side-effect-free and never throws, so the dry-run
     * verdict is identical to {@see evaluate()}.
     */
    public function precheck(TransitionContext $context): GuardResult
    {
        return $this->evaluate($context);
    }
}

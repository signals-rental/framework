<?php

namespace App\Guards\Opportunities\Stages;

use App\Guards\Opportunities\Contracts\GuardStage;
use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\TransitionContext;
use App\Services\Opportunities\TransitionRuleRegistry;

/**
 * Stage 3 of the guard pipeline — Business Rules (opportunity-lifecycle.md §12.2
 * "Business rule guards"). REAL + config-driven.
 *
 * Evaluates the {@see TransitionRuleRegistry}'s rules that {@see appliesTo} the
 * current transition, in registration order, stopping at the first denial. The
 * stage itself contains NO transition-specific logic — it is a generic driver over
 * the registered rules, so the set of business rules is extended purely by
 * registration (Ben's locked steer: never a hardcoded named-status matrix).
 *
 * Rules may throw their own ValidationException (the shortage gate does, to list
 * the offending shortages) — those propagate as a 422 unchanged. A rule that
 * returns {@see GuardResult::deny()} instead is surfaced as a denial by the
 * pipeline.
 */
class BusinessRulesStage implements GuardStage
{
    public function __construct(private readonly TransitionRuleRegistry $rules) {}

    public function evaluate(TransitionContext $context): GuardResult
    {
        foreach ($this->rules->applicableTo($context) as $rule) {
            $result = $rule->evaluate($context);

            if ($result->denied()) {
                return $result;
            }
        }

        return GuardResult::allow();
    }
}

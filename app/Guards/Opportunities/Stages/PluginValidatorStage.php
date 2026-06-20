<?php

namespace App\Guards\Opportunities\Stages;

use App\Guards\Opportunities\Contracts\GuardStage;
use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\PluginValidatorRegistry;
use App\Guards\Opportunities\TransitionContext;

/**
 * Stage 4 of the guard pipeline — Plugin Validators (opportunity-lifecycle.md
 * §12.2 "Plugin guards"). PLACEHOLDER seam.
 *
 * Evaluates any plugin-registered {@see App\Guards\Opportunities\Contracts\TransitionRule}
 * validators applicable to the transition, after core business rules and before
 * the Verbs `validate()` hard-invariant layer. Empty in core — no validators are
 * registered — so it always allows today; the {@see PluginValidatorRegistry} is
 * the seam a plugin attaches to (via the Plugin SDK validator hook) with no
 * pipeline change.
 */
class PluginValidatorStage implements GuardStage
{
    public function __construct(private readonly PluginValidatorRegistry $validators) {}

    public function evaluate(TransitionContext $context): GuardResult
    {
        foreach ($this->validators->applicableTo($context) as $validator) {
            $result = $validator->evaluate($context);

            if ($result->denied()) {
                return $result;
            }
        }

        return GuardResult::allow();
    }

    /**
     * Dry-run: ask each applicable plugin validator for its side-effect-free
     * {@see TransitionRule::precheck()} verdict. Empty in core, so it allows.
     */
    public function precheck(TransitionContext $context): GuardResult
    {
        foreach ($this->validators->applicableTo($context) as $validator) {
            $result = $validator->precheck($context);

            if ($result->denied()) {
                return $result;
            }
        }

        return GuardResult::allow();
    }
}

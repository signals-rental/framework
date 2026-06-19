<?php

namespace App\Guards\Opportunities;

use App\Guards\Opportunities\Stages\AutoApprovalGate;
use App\Guards\Opportunities\Stages\BusinessRulesStage;
use App\Guards\Opportunities\Stages\PermissionStage;
use App\Guards\Opportunities\Stages\PluginValidatorStage;
use Illuminate\Validation\ValidationException;

/**
 * The opportunity transition guard pipeline (opportunity-lifecycle.md §12.2).
 *
 * Actions invoke {@see run()} BEFORE firing a transition's Verbs event(s). The
 * pipeline composes the four configurable guard stages in the spec's fixed order
 * and stops at the first denial:
 *
 *   1. Permission       — REAL: Gate::authorize on the declared ability (403).
 *   2. Approval         — PLACEHOLDER seam: ApprovalGate (no-op default).
 *   3. Business Rules   — REAL + config-driven: the TransitionRuleRegistry rules
 *                         applicable to the transition (shortage gate, FX/tax
 *                         lock), matched generically by transition key.
 *   4. Plugin Validators— PLACEHOLDER seam: the PluginValidatorRegistry.
 *
 * Verbs `validate()` then runs as the FINAL hard-invariant layer inside fire() —
 * the empty-deal / on-hire / isClosed structural invariants STAY there (they are
 * not config rules and the pipeline does not duplicate them).
 *
 * The pipeline composes existing pieces; it builds NO approval/workflow/plugin
 * engine. Stages may throw their own native exception (Permission →
 * AuthorizationException, a business rule → ValidationException); a stage that
 * instead returns a structured denial is converted here into a 422
 * ValidationException so callers get one consistent failure shape.
 */
class GuardPipeline
{
    public function __construct(
        private readonly PermissionStage $permission,
        private readonly AutoApprovalGate $approval,
        private readonly BusinessRulesStage $businessRules,
        private readonly PluginValidatorStage $pluginValidators,
    ) {}

    /**
     * Run every stage in order against the transition. Returns normally when the
     * transition is allowed; throws (AuthorizationException / ValidationException)
     * when a stage denies it.
     *
     * @throws ValidationException
     */
    public function run(TransitionContext $context): void
    {
        $stages = [
            $this->permission,
            $this->approval,
            $this->businessRules,
            $this->pluginValidators,
        ];

        foreach ($stages as $stage) {
            $result = $stage->evaluate($context);

            if ($result->denied()) {
                throw ValidationException::withMessages(
                    $result->errors !== []
                        ? $result->errors
                        : ['transition' => ["This transition was blocked at the {$result->stage} stage."]],
                );
            }
        }
    }
}

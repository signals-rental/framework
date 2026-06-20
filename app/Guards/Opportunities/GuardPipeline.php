<?php

namespace App\Guards\Opportunities;

use App\Guards\Opportunities\Contracts\GuardStage;
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
 *
 * {@see check()} is the non-throwing, side-effect-free counterpart that powers
 * the `available_actions` endpoint: it runs the same stages via their
 * {@see GuardStage::precheck()} verdicts and RETURNS the first denial (carrying a
 * machine-readable {@see GuardResult::$code}) instead of throwing or mutating —
 * so the UI can render a permission/guard-aware toolbar before any write.
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
     * The throwing, side-effecting path: the permission stage authorises (a 403 on
     * failure) and the business-rule stage runs the rules' full {@see
     * \App\Guards\Opportunities\Contracts\TransitionRule::evaluate()} (auto-resolve,
     * acknowledgement recording, the shortage gate's own 422). A stage that returns
     * a structured denial rather than throwing is normalised here to a 422.
     *
     * @throws ValidationException
     */
    public function run(TransitionContext $context): void
    {
        foreach ($this->stages() as $stage) {
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

    /**
     * Dry-run every stage in order WITHOUT side effects and WITHOUT throwing, via
     * each stage's {@see GuardStage::precheck()}. Returns the first denial (with
     * its machine-readable {@see GuardResult::$code}) or {@see GuardResult::allow()}
     * when the transition would pass.
     *
     * This is the read-model the `available_actions` endpoint builds the Show-page
     * toolbar from: it tells the UI whether a transition WOULD be permitted, and
     * WHY not, without performing it (no permission 403, no auto-resolution, no
     * acknowledgement). It does not run the Verbs `validate()` hard invariants —
     * those are structural and only enforceable at fire() time.
     */
    public function check(TransitionContext $context): GuardResult
    {
        foreach ($this->stages() as $stage) {
            $result = $stage->precheck($context);

            if ($result->denied()) {
                return $result;
            }
        }

        return GuardResult::allow();
    }

    /**
     * The four guard stages in their fixed spec order. Shared by {@see run()} and
     * {@see check()} so the composition is declared once.
     *
     * @return list<GuardStage>
     */
    private function stages(): array
    {
        return [
            $this->permission,
            $this->approval,
            $this->businessRules,
            $this->pluginValidators,
        ];
    }
}

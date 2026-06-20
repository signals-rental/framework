<?php

namespace App\Guards\Opportunities\Contracts;

use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\TransitionContext;

/**
 * One ordered stage of the opportunity transition guard pipeline
 * (opportunity-lifecycle.md §12.2). The pipeline runs stages in a fixed order —
 * Permission → Approval → Business Rules → Plugin Validators — and stops at the
 * first denial.
 *
 * A stage MAY throw its own native exception when that is the faithful behaviour
 * (the permission stage throws an AuthorizationException; the business-rule stage
 * throws a 422 ValidationException). Otherwise it returns a {@see GuardResult}.
 * Returning {@see GuardResult::allow()} passes control to the next stage.
 */
interface GuardStage
{
    public function evaluate(TransitionContext $context): GuardResult;

    /**
     * Evaluate the stage WITHOUT side effects and WITHOUT throwing — the dry-run
     * counterpart of {@see evaluate()} backing {@see GuardPipeline::check()} and
     * the `available_actions` endpoint. A stage that would normally throw its
     * native exception (Permission → AuthorizationException, the shortage rule →
     * ValidationException) instead returns a {@see GuardResult::deny()} carrying a
     * machine-readable code, and a stage with side effects (recording an
     * acknowledgement, auto-resolving) performs NONE of them. The decision must
     * otherwise match {@see evaluate()} exactly.
     */
    public function precheck(TransitionContext $context): GuardResult;
}

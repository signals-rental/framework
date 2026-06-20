<?php

namespace App\Guards\Opportunities\Stages;

use App\Guards\Opportunities\Contracts\ApprovalGate;
use App\Guards\Opportunities\Contracts\GuardStage;
use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\TransitionContext;

/**
 * Stage 2 of the guard pipeline — Approval (opportunity-lifecycle.md §12.2
 * "Approval guards"). PLACEHOLDER — the OSS default that always approves.
 *
 * This is the no-op implementation of the {@see ApprovalGate} seam: it lets every
 * transition through. The seam is REAL and registered (bound in AppServiceProvider
 * and invoked by the pipeline), so a future approval-chain consumer rebinds
 * {@see ApprovalGate} to an implementation that creates approval requests and
 * denies transitions pending approval — with ZERO change to the pipeline or the
 * lifecycle events.
 */
class AutoApprovalGate implements ApprovalGate, GuardStage
{
    public function evaluate(TransitionContext $context): GuardResult
    {
        // No approval chains are wired in the OSS core — every transition is
        // implicitly approved. A future approval engine rebinds the ApprovalGate
        // contract to override this.
        return GuardResult::allow();
    }

    /**
     * Dry-run: the no-op approval gate always allows, identically to
     * {@see evaluate()}. A future approval engine overrides this to report
     * "approval required" without creating an approval request.
     */
    public function precheck(TransitionContext $context): GuardResult
    {
        return GuardResult::allow();
    }
}

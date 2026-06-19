<?php

namespace App\Guards\Opportunities\Contracts;

use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\TransitionContext;

/**
 * The approval stage's extension point (opportunity-lifecycle.md §12.2 "Approval
 * guards") — PLACEHOLDER.
 *
 * The approval-chain consumer (a separate, as-yet-unbuilt engine) is the intended
 * implementor: when an approval rule matches a transition, it intercepts here,
 * creates an approval request, and denies the transition until the approval is
 * granted. The framework ships the {@see App\Guards\Opportunities\Stages\AutoApprovalGate}
 * no-op default (always approves), so the stage is a REAL, registered seam today
 * — a future approval engine binds this contract with ZERO retrofit to the
 * pipeline or the events.
 */
interface ApprovalGate
{
    public function evaluate(TransitionContext $context): GuardResult;
}

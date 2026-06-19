<?php

namespace App\Guards\Opportunities\Contracts;

use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\TransitionContext;

/**
 * A single config-driven business rule evaluated by the business-rules stage of
 * the guard pipeline (opportunity-lifecycle.md §12.2 "Business rule guards").
 *
 * Rules are registered in the {@see App\Services\Opportunities\TransitionRuleRegistry}
 * (mirroring the DemandSource / ShortageResolver registries) and matched
 * GENERICALLY by transition key — NEVER by a hardcoded named-status matrix (Ben's
 * locked steer). The pipeline asks each registered rule whether it
 * {@see appliesTo()} the current {@see TransitionContext}; applicable rules
 * {@see evaluate()} and the first denial stops the transition.
 *
 * Rules may have side effects appropriate to the guard layer (e.g. the shortage
 * gate records an acknowledgement on a Warn outcome) — they run BEFORE the Verbs
 * event fires, inside the action's atomic transaction, so a denial leaves nothing
 * persisted. Hard structural invariants (the empty-deal / on-hire / isClosed
 * checks) stay in the events' Verbs `validate()` and are NOT expressed as rules.
 */
interface TransitionRule
{
    /**
     * A stable key identifying this rule (for diagnostics / registry lookup).
     */
    public function key(): string;

    /**
     * Whether this rule participates in the given transition. Implementations
     * match on {@see TransitionContext::$transition} and/or the change flags —
     * never on a hardcoded status name.
     */
    public function appliesTo(TransitionContext $context): bool;

    /**
     * Evaluate the rule. Return {@see GuardResult::allow()} to pass, or
     * {@see GuardResult::deny()} (or throw a ValidationException) to block. May
     * perform guard-layer side effects (e.g. recording an acknowledgement).
     */
    public function evaluate(TransitionContext $context): GuardResult;
}

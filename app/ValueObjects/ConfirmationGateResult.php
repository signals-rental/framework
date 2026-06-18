<?php

namespace App\ValueObjects;

use App\Enums\ShortagePolicy;

/**
 * The outcome of evaluating the shortage confirmation gate
 * (shortage-resolution-sub-hires.md §7). The decision the gate reaches after
 * applying the store policy and the actor's `can_ignore_shortages` permission to
 * the detected shortages.
 *
 *  - `decision` is the EFFECTIVE policy after the permission override (§7.2):
 *    Block, Warn, or Allow.
 *  - `shortages` is the detected (unresolved) collection driving the decision.
 *  - `acknowledgementRequired` is true when proceeding must record an
 *    acknowledgement (Warn outcomes, and Block→Warn permission overrides).
 *  - `permissionUsed` records whether `can_ignore_shortages` relaxed the gate.
 *
 * Designed as a precursor to the M7 guard pipeline: the same shape backs the
 * dispatch gate (M5) once dispatch events exist.
 */
final readonly class ConfirmationGateResult
{
    public function __construct(
        public ShortagePolicy $decision,
        public ShortageCollection $shortages,
        public ShortagePolicy $storePolicy,
        public bool $permissionUsed,
    ) {}

    /**
     * Whether the gate blocks the transition (unresolved shortages + an effective
     * Block decision).
     */
    public function blocks(): bool
    {
        return $this->decision === ShortagePolicy::Block && $this->shortages->hasUnresolved();
    }

    /**
     * Whether proceeding past this gate must record an acknowledgement: a Warn
     * decision with unresolved shortages (§7.3).
     */
    public function acknowledgementRequired(): bool
    {
        return $this->decision === ShortagePolicy::Warn && $this->shortages->hasUnresolved();
    }
}

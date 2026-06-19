<?php

namespace App\Enums;

use App\Models\Demand;

/**
 * The five fixed behavioural phases a demand can occupy.
 *
 * Every demand source (opportunity item, quarantine, store transfer, plugin
 * source, …) maps its own internal states down to exactly one of these phases.
 * The phase is the ONLY thing that determines a demand's impact on availability:
 *
 *  - {@see DemandPhase::Committed} and {@see DemandPhase::Operational} are
 *    **active** — they reduce available quantity.
 *  - {@see DemandPhase::Draft}, {@see DemandPhase::Closed}, and
 *    {@see DemandPhase::Void} are **inactive** — they do not.
 *
 * The `demands.is_active` boolean column caches {@see isActive()} for fast
 * range-overlap queries and the partial GiST exclusion constraint.
 *
 * @see Demand
 * @see OpportunityStatus::phase()
 */
enum DemandPhase: string
{
    /** Being composed, provisional, not yet confirmed. No availability impact. */
    case Draft = 'draft';

    /**
     * Soft-reserved while a confirmed/reserved booking is on hold (a postponed
     * quotation). The unit is still claimed — availability-engine.md retains
     * postponed demand as `held` rather than releasing it — but the hold is not an
     * operational hire. Active (reduces availability) yet distinct from Committed/
     * Operational so it can be reported and re-derived separately.
     */
    case Held = 'held';

    /** Confirmed, resources reserved. Active — reduces availability. */
    case Committed = 'committed';

    /** In progress, items may be dispatched. Active — reduces availability. */
    case Operational = 'operational';

    /** Complete, items returned. Inactive (after the turnaround period). */
    case Closed = 'closed';

    /** Cancelled or rejected. Inactive immediately, no turnaround applied. */
    case Void = 'void';

    /**
     * Whether a demand in this phase consumes availability.
     *
     * True for the Held, Committed and Operational phases (each holds a unit);
     * every other phase is inactive and excluded from availability calculations
     * and the serialised exclusion constraint.
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::Held, self::Committed, self::Operational => true,
            self::Draft, self::Closed, self::Void => false,
        };
    }

    /**
     * Whether a demand in this phase physically occupies stock such that a
     * turnaround (post-rent) buffer should be applied to its unavailable window.
     *
     * True for the phases that hold a physical unit — {@see DemandPhase::Committed}
     * and {@see DemandPhase::Operational} (the active phases), and
     * {@see DemandPhase::Closed} (the item has returned but the product's
     * turnaround/cleaning window still occupies it). The buffer is only meaningful
     * while the unit is, or has just been, physically present.
     *
     * False for {@see DemandPhase::Draft} (provisional — never reserved a unit),
     * {@see DemandPhase::Held} (a soft hold on a postponed quote — the unit is
     * claimed but not physically out, so no turnaround/cleaning window applies) and
     * {@see DemandPhase::Void} (cancelled/rejected — releases immediately with no
     * turnaround, per availability-engine.md §"Turnaround Time": "Turnaround does
     * not apply when a demand enters the Void phase").
     *
     * The plan's reference enum lists only `Closed` here because that is the phase
     * transition that *extends* the period at return time; Signals applies the
     * same buffer at creation for the active phases too (so the period always
     * reflects the full unavailable window — availability-engine.md §"Turnaround
     * Time": buffers "baked into the demand's period range at creation time"),
     * hence Committed/Operational also apply it.
     */
    public function appliesTurnaround(): bool
    {
        return match ($this) {
            self::Committed, self::Operational, self::Closed => true,
            self::Draft, self::Held, self::Void => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Held => 'Held',
            self::Committed => 'Committed',
            self::Operational => 'Operational',
            self::Closed => 'Closed',
            self::Void => 'Void',
        };
    }
}

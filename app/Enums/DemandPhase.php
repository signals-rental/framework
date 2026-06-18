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
     * True only for the Committed and Operational phases; every other phase is
     * inactive and excluded from availability calculations and the serialised
     * exclusion constraint.
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::Committed, self::Operational => true,
            self::Draft, self::Closed, self::Void => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Committed => 'Committed',
            self::Operational => 'Operational',
            self::Closed => 'Closed',
            self::Void => 'Void',
        };
    }
}

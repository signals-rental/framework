<?php

namespace App\Enums;

use App\Services\Availability\SlotCalculator;

/**
 * Granularity at which the availability engine snapshots demand and stock.
 *
 * The resolution fixes the time bucket size for demand windows and availability
 * snapshots. It is immutable once availability data exists, because changing it
 * would require migrating every existing snapshot to the new bucket size.
 */
enum AvailabilityResolution: string
{
    case Hourly = 'hourly';
    case HalfDaily = 'half_daily';
    case Daily = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::HalfDaily => 'Half-daily',
            self::Daily => 'Daily',
        };
    }

    /**
     * The length of a single slot, in whole minutes.
     *
     * Read-side helper for UI consumers interpreting slot windows at the
     * configured resolution (calendar/Gantt rendering, tooltip durations). The
     * authoritative slot-boundary maths lives in
     * {@see SlotCalculator}; this is a pure,
     * dependency-free derivation of the same intervals for presentation.
     */
    public function slotMinutes(): int
    {
        return match ($this) {
            self::Hourly => 60,
            self::HalfDaily => 360,
            self::Daily => 1440,
        };
    }

    /**
     * The length of a single slot, in whole hours.
     */
    public function slotHours(): int
    {
        return intdiv($this->slotMinutes(), 60);
    }

    /**
     * How many slots make up one calendar day at this resolution — the column
     * count a day occupies in a slot-resolution Gantt/timeline grid.
     */
    public function slotsPerDay(): int
    {
        return intdiv(1440, $this->slotMinutes());
    }

    /**
     * Whether this resolution buckets at (or coarser than) a whole day, so a
     * calendar consumer can render one cell per day directly from the daily
     * summary rather than expanding intra-day slots.
     */
    public function isDailyOrCoarser(): bool
    {
        return $this->slotMinutes() >= 1440;
    }
}

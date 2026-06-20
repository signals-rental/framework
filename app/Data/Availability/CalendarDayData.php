<?php

namespace App\Data\Availability;

use App\Models\AvailabilityDailySummary;
use Spatie\LaravelData\Data;

/**
 * A single calendar cell: a product's availability on one day at a store.
 *
 * `available` is the day's worst (`min_available`) availability — the
 * conservative figure a calendar cell shows ("can I still take this that day?")
 * — and `has_shortage` flags any dip below zero during the day.
 * `pending_checkin` is the day's peak count of units physically returned but not
 * yet inspected (informational — it never reduces `available`).
 */
class CalendarDayData extends Data
{
    public function __construct(
        public string $date,
        public int $available,
        public bool $has_shortage,
        public int $pending_checkin,
    ) {}

    public static function fromSummary(AvailabilityDailySummary $summary): self
    {
        return new self(
            date: $summary->date->toDateString(),
            available: $summary->min_available,
            has_shortage: $summary->has_shortage,
            pending_checkin: $summary->pending_checkin_quantity,
        );
    }
}

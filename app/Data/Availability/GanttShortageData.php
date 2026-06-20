<?php

namespace App\Data\Availability;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * A shortage window on the availability Gantt: a day on which availability dipped
 * below zero, with its severity (depth of the shortage) and whether it falls
 * wholly within a prep/turnaround buffer zone.
 *
 * `in_buffer_zone` is the industry-feedback signal: when true the shorted units
 * are physically in the building (in prep or turnaround) rather than on an active
 * hire, so the conflict may be resolvable by adjusting prep timing without moving
 * any booking dates.
 */
class GanttShortageData extends Data
{
    public function __construct(
        public string $from,
        public string $to,
        public int $severity,
        public bool $in_buffer_zone,
    ) {}

    /**
     * Build a single-day shortage window. `$minAvailable` is the day's worst
     * availability (negative); `severity` is its magnitude.
     */
    public static function make(CarbonInterface $day, int $minAvailable, bool $inBufferZone): self
    {
        return new self(
            from: $day->toDateString(),
            to: $day->toDateString(),
            // `severity` is a positive magnitude (depth of the shortage), mirroring
            // StoreShortageData — never the raw negative `min_available`.
            severity: max(0, -$minAvailable),
            in_buffer_zone: $inBufferZone,
        );
    }
}

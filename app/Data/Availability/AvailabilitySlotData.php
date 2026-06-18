<?php

namespace App\Data\Availability;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\AvailabilitySnapshot;
use Spatie\LaravelData\Data;

/**
 * A single resolution-aligned availability slot within a range response.
 */
class AvailabilitySlotData extends Data
{
    use FormatsTimestamps;

    /**
     * @param  array<string, int>  $demand_breakdown
     */
    public function __construct(
        public string $slot_start,
        public int $total_stock,
        public int $total_demanded,
        public int $available,
        public array $demand_breakdown,
    ) {}

    public static function fromModel(AvailabilitySnapshot $snapshot): self
    {
        return new self(
            slot_start: self::formatTimestamp($snapshot->slot_start),
            total_stock: $snapshot->total_stock,
            total_demanded: $snapshot->total_demanded,
            available: $snapshot->available,
            demand_breakdown: $snapshot->demand_breakdown,
        );
    }
}

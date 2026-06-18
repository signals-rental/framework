<?php

namespace App\Data\Availability;

use App\Data\Concerns\FormatsTimestamps;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * Availability for a single product/store over a date range, read from
 * pre-calculated snapshots.
 *
 * Each slot in `slots` is resolution-aligned. `min_available` /
 * `max_available` summarise the range; `calculated_at` is the oldest snapshot
 * recalculation time in the range, exposing how fresh the snapshot data is
 * (null when the range has no snapshots yet).
 */
class AvailabilityRangeData extends Data
{
    use FormatsTimestamps;

    /**
     * @param  list<AvailabilitySlotData>  $slots
     */
    public function __construct(
        public int $product_id,
        public int $store_id,
        public string $from,
        public string $to,
        public ?int $min_available,
        public ?int $max_available,
        public ?string $calculated_at,
        public array $slots,
    ) {}

    /**
     * @param  list<AvailabilitySlotData>  $slots
     */
    public static function make(
        int $productId,
        int $storeId,
        CarbonInterface $from,
        CarbonInterface $to,
        array $slots,
        ?CarbonInterface $calculatedAt,
    ): self {
        $availabilities = array_map(static fn (AvailabilitySlotData $slot): int => $slot->available, $slots);

        return new self(
            product_id: $productId,
            store_id: $storeId,
            from: self::formatTimestamp($from),
            to: self::formatTimestamp($to),
            min_available: $availabilities === [] ? null : min($availabilities),
            max_available: $availabilities === [] ? null : max($availabilities),
            calculated_at: $calculatedAt !== null ? self::formatTimestamp($calculatedAt) : null,
            slots: $slots,
        );
    }
}

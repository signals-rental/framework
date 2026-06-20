<?php

namespace App\Data\Availability;

use App\Models\AvailabilityDailySummary;
use Spatie\LaravelData\Data;

/**
 * One product row of the availability calendar grid: the product and its ordered
 * per-day availability cells across the requested window.
 */
class CalendarProductData extends Data
{
    /**
     * @param  list<CalendarDayData>  $days
     */
    public function __construct(
        public int $product_id,
        public ?string $product_name,
        public array $days,
    ) {}

    /**
     * Build a product row from its daily summary rows (assumed pre-ordered by
     * date).
     *
     * @param  list<AvailabilityDailySummary>  $summaries
     */
    public static function fromSummaries(int $productId, ?string $productName, array $summaries): self
    {
        $days = array_map(
            static fn (AvailabilityDailySummary $summary): CalendarDayData => CalendarDayData::fromSummary($summary),
            $summaries,
        );

        return new self(
            product_id: $productId,
            product_name: $productName,
            days: $days,
        );
    }
}

<?php

namespace App\Data\Availability;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * Point-in-time availability for a single product at a single store.
 *
 * Computed on-the-fly from the `demands` table (no snapshot dependency):
 * `available = total_stock - total_demanded` for the queried date's slot, with
 * a per-source `demand_breakdown`. `available` may be negative (a shortage).
 */
class AvailabilityData extends Data
{
    /**
     * @param  array<string, int>  $demand_breakdown
     */
    public function __construct(
        public int $product_id,
        public int $store_id,
        public string $date,
        public int $total_stock,
        public int $total_demanded,
        public int $available,
        public array $demand_breakdown,
    ) {}

    /**
     * @param  array<string, int>  $demandBreakdown
     */
    public static function make(
        int $productId,
        int $storeId,
        Carbon $date,
        int $totalStock,
        int $totalDemanded,
        array $demandBreakdown,
    ): self {
        return new self(
            product_id: $productId,
            store_id: $storeId,
            date: $date->toDateString(),
            total_stock: $totalStock,
            total_demanded: $totalDemanded,
            available: $totalStock - $totalDemanded,
            demand_breakdown: $demandBreakdown,
        );
    }
}

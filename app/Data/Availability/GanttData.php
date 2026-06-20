<?php

namespace App\Data\Availability;

use App\Data\Concerns\FormatsTimestamps;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * The Gantt read model for one product at a store over a window: the individual
 * demand bars and the shortage windows, read directly from the `demands` table
 * (availability-engine.md §"Gantt Chart View").
 *
 * Unlike the snapshot-backed range/calendar reads, the Gantt shows individual
 * demands rather than aggregated availability, so the response surfaces each
 * demand decomposed into its prep / on-hire / turnaround zones.
 */
class GanttData extends Data
{
    use FormatsTimestamps;

    /**
     * @param  list<GanttDemandBarData>  $demands
     * @param  list<GanttShortageData>  $shortages
     */
    public function __construct(
        public int $product_id,
        public int $store_id,
        public string $from,
        public string $to,
        public int $total_stock,
        public array $demands,
        public array $shortages,
    ) {}

    /**
     * @param  list<GanttDemandBarData>  $demands
     * @param  list<GanttShortageData>  $shortages
     */
    public static function make(
        int $productId,
        int $storeId,
        Carbon $from,
        Carbon $to,
        int $totalStock,
        array $demands,
        array $shortages,
    ): self {
        return new self(
            product_id: $productId,
            store_id: $storeId,
            from: self::formatTimestamp($from),
            to: self::formatTimestamp($to),
            total_stock: $totalStock,
            demands: $demands,
            shortages: $shortages,
        );
    }
}

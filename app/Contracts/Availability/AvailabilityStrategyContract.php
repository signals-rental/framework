<?php

namespace App\Contracts\Availability;

use App\Models\Demand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Plugin extension point for the availability recalculation pipeline.
 *
 * Exactly one strategy is active at a time (the OSS default is a pass-through
 * no-op; a plugin may rebind it). The two hooks bracket the per-slot
 * availability calculation in {@see App\Services\Availability\RecalculationPipeline}:
 *
 *  - {@see preCalculation()} (recalc step 3) runs after active demands are
 *    gathered but before per-slot availability is computed. A strategy may add
 *    synthetic demands (buffer stock, sub-hire augmentation), drop demands, or
 *    adjust quantities — returning the demand set the pipeline then sums.
 *  - {@see postCalculation()} (recalc step 5) runs after per-slot availability is
 *    computed but before snapshots are written. A strategy may clamp or floor the
 *    final numbers (minimum buffer stock, hard caps).
 *
 * Tenant-ignorant: the contract carries product/store/window scalars and the
 * computed data only — no tenancy, no auth, no settings.
 *
 * Both hooks MUST be pure and side-effect free with respect to the database; the
 * pipeline owns all persistence. A strategy that throws or misbehaves should be
 * treated by the caller as a pass-through (the OSS default never throws).
 */
interface AvailabilityStrategyContract
{
    /**
     * Adjust the active demand set before per-slot availability is calculated.
     *
     * @param  Collection<int, Demand>  $demands  the active demands gathered for the window
     * @return Collection<int, Demand> the (possibly adjusted) demand set to sum
     */
    public function preCalculation(
        int $productId,
        int $storeId,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        Collection $demands,
    ): Collection;

    /**
     * Adjust the per-slot availability results before snapshots are written.
     *
     * Each entry is `['slot_start' => Carbon, 'total_stock' => int,
     * 'total_demanded' => int, 'available' => int, 'breakdown' => array<string,int>]`.
     * A strategy returns the same shape, with adjusted numbers as needed.
     *
     * @param  Collection<int, array{slot_start: Carbon, total_stock: int, total_demanded: int, available: int, breakdown: array<string, int>}>  $slotResults
     * @return Collection<int, array{slot_start: Carbon, total_stock: int, total_demanded: int, available: int, breakdown: array<string, int>}>
     */
    public function postCalculation(
        int $productId,
        int $storeId,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        Collection $slotResults,
    ): Collection;
}

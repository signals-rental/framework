<?php

namespace App\Services;

use App\Data\Availability\AvailabilityData;
use App\Data\Availability\AvailabilityRangeData;
use App\Data\Availability\AvailabilitySlotData;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use App\Services\Availability\SlotCalculator;
use BadMethodCallException;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The read interface for the availability engine. Implements the two-tier read
 * strategy from the design:
 *
 *  1. **Point queries** ({@see getAvailability()}, {@see checkAvailability()})
 *     compute on-the-fly from the `demands` table — zero snapshot dependency.
 *  2. **Range/grid queries** ({@see getAvailabilityRange()}) read from
 *     pre-calculated `availability_snapshots`, surfacing `calculated_at` so
 *     consumers know how fresh the data is.
 *
 * Resolve via the container so the slot calculator's resolution provider can be
 * rebound (e.g. by the Cloud package).
 */
class AvailabilityService
{
    public function __construct(
        private readonly SlotCalculator $slotCalculator,
        private readonly RecalculationPipeline $pipeline,
    ) {}

    /**
     * Point availability for a product/store on a given date — computed live
     * from demands. The date is aligned to its resolution slot; demand
     * overlapping that slot reduces availability.
     */
    public function getAvailability(int $productId, int $storeId, Carbon $date): AvailabilityData
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            return AvailabilityData::make($productId, $storeId, $date, 0, 0, []);
        }

        $timezone = $this->storeTimezone($storeId);
        $slotStart = $this->slotCalculator->alignToSlot($date, $timezone);
        $slotEnd = $this->slotCalculator->advance($slotStart, $timezone);

        $totalStock = $this->pipeline->totalStock($product, $storeId);

        [$demanded, $breakdown] = $this->sumDemand($productId, $storeId, $slotStart, $slotEnd);

        return AvailabilityData::make($productId, $storeId, $slotStart, $totalStock, $demanded, $breakdown);
    }

    /**
     * Range availability for a product/store, read from snapshots. Slots are
     * returned in chronological order; `calculated_at` reflects the oldest
     * snapshot in the range (null when none exist yet).
     */
    public function getAvailabilityRange(int $productId, int $storeId, Carbon $from, Carbon $to): AvailabilityRangeData
    {
        /** @var Collection<int, AvailabilitySnapshot> $snapshots */
        $snapshots = AvailabilitySnapshot::query()
            ->forProductStore($productId, $storeId)
            ->inWindow($from, $to)
            ->orderBy('slot_start')
            ->get();

        $slots = $snapshots
            ->map(static fn (AvailabilitySnapshot $snapshot): AvailabilitySlotData => AvailabilitySlotData::fromModel($snapshot))
            ->values()
            ->all();

        $calculatedAt = $snapshots
            ->map(static fn (AvailabilitySnapshot $snapshot): CarbonInterface => $snapshot->calculated_at)
            ->min();

        return AvailabilityRangeData::make($productId, $storeId, $from, $to, $slots, $calculatedAt);
    }

    /**
     * Whether at least `$quantity` units are available for the entire
     * `[$from, $to)` window. Computed live from demands across every slot the
     * window touches — true only if the worst slot still has the quantity.
     */
    public function checkAvailability(int $productId, int $storeId, Carbon $from, Carbon $to, int $quantity): bool
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            return false;
        }

        $timezone = $this->storeTimezone($storeId);
        $totalStock = $this->pipeline->totalStock($product, $storeId);

        /** @var Collection<int, Demand> $demands */
        $demands = Demand::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->overlapping($from, $to)
            ->get();

        foreach ($this->slotCalculator->generateSlots($from, $to, $timezone) as $slotStart) {
            $slotEnd = $this->slotCalculator->advance($slotStart, $timezone);
            [$demanded] = $this->sumDemandIn($demands, $slotStart, $slotEnd);

            if ($totalStock - $demanded < $quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Products in a store/range whose availability is negative.
     *
     * Deferred to M3 (shortage detection + resolution). Declared so the
     * AvailabilityService surface matches the design and callers fail loudly
     * rather than silently no-op.
     *
     * @return never
     */
    public function getShortages(int $storeId, Carbon $from, Carbon $to): mixed
    {
        throw new BadMethodCallException('Shortage queries are not implemented until M3.');
    }

    /**
     * Composed availability for a kit product from its component snapshots.
     *
     * Deferred to M5 (kit/serialised-container availability). Declared so the
     * surface matches the design and callers fail loudly.
     *
     * @return never
     */
    public function getKitAvailability(int $productId, int $storeId, Carbon $from, Carbon $to): mixed
    {
        throw new BadMethodCallException('Kit availability is not implemented until M5.');
    }

    /**
     * Sum active demand overlapping `[slotStart, slotEnd)` for a product/store,
     * with a per-source breakdown.
     *
     * @return array{0: int, 1: array<string, int>}
     */
    private function sumDemand(int $productId, int $storeId, Carbon $slotStart, Carbon $slotEnd): array
    {
        /** @var Collection<int, Demand> $demands */
        $demands = Demand::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->overlapping($slotStart, $slotEnd)
            ->get();

        return $this->sumDemandIn($demands, $slotStart, $slotEnd);
    }

    /**
     * Sum the demand in a pre-fetched collection overlapping a slot.
     *
     * @param  Collection<int, Demand>  $demands
     * @return array{0: int, 1: array<string, int>}
     */
    private function sumDemandIn(Collection $demands, Carbon $slotStart, Carbon $slotEnd): array
    {
        $total = 0;
        $breakdown = [];

        foreach ($demands as $demand) {
            if (! ($demand->starts_at->lessThan($slotEnd) && $demand->ends_at->greaterThan($slotStart))) {
                continue;
            }

            $returned = max(0, (int) ($demand->metadata['returned_quantity'] ?? 0));
            $quantity = max(0, $demand->quantity - $returned);

            if ($quantity <= 0) {
                continue;
            }

            $total += $quantity;
            $breakdown[$demand->source_type] = ($breakdown[$demand->source_type] ?? 0) + $quantity;
        }

        return [$total, $breakdown];
    }

    /**
     * The IANA timezone for a store, falling back to the application timezone.
     */
    private function storeTimezone(int $storeId): ?string
    {
        return Store::query()->whereKey($storeId)->value('timezone');
    }
}

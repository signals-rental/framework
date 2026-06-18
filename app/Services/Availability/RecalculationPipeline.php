<?php

namespace App\Services\Availability;

use App\Enums\AvailabilityEventType;
use App\Enums\StockCategory;
use App\Models\AvailabilityEvent;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Observers\DemandObserver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Recomputes availability snapshots for a product/store over a date range.
 *
 * This is the M2 **synchronous** pipeline: it gathers stock and active demands,
 * computes per-slot availability via the {@see SlotCalculator}, upserts the
 * affected `availability_snapshots` rows, and appends an
 * `availability_recalculated` event. It is invoked synchronously by the
 * {@see DemandObserver} whenever demands change.
 *
 * Deferred to M3 (intentionally NOT built here): async/queued recalculation,
 * debouncing, daily-summary rollups, shortage detection events, stock-change
 * triggers, and Reverb/webhook broadcast. The pipeline accepts a bounded
 * `(product, store, from, to)` blast radius so a single demand change never
 * forces a full rebuild.
 */
class RecalculationPipeline
{
    public function __construct(
        private readonly SlotCalculator $slotCalculator,
    ) {}

    /**
     * Recalculate snapshots for the product/store over the half-open window
     * `[$from, $to)`. No-op for products that do not track availability.
     *
     * The window is first clamped to the rolling snapshot horizon (config
     * `availability.snapshot_horizon`) so an indefinite/sentinel-dated demand
     * cannot force the pipeline to materialise an unbounded number of slots in a
     * single synchronous request. Slots outside the horizon are still served
     * correctly by the on-the-fly point query
     * ({@see AvailabilityService::getAvailability()}), which reads `demands`
     * directly and never depends on snapshots — so clamping preserves
     * correctness while bounding the write cost.
     *
     * On PostgreSQL the work is serialised per product/store with a
     * transaction-scoped advisory lock so concurrent recalculations cannot
     * interleave their upserts. On SQLite the lock is a no-op.
     */
    public function recalculate(int $productId, int $storeId, Carbon $from, Carbon $to): void
    {
        $product = Product::query()->find($productId);

        if ($product === null || ! $product->track_availability) {
            return;
        }

        [$from, $to] = $this->clampToHorizon($from, $to);

        // The entire requested window lies outside the rolling horizon — there
        // is nothing to materialise; point queries cover it on the fly.
        if (! $from->lessThan($to)) {
            return;
        }

        $connection = (new AvailabilitySnapshot)->getConnection();
        $usesPostgres = $connection->getDriverName() === 'pgsql';

        $work = function () use ($product, $productId, $storeId, $from, $to): void {
            $timezone = $this->storeTimezone($storeId);

            $totalStock = $this->totalStock($product, $storeId);
            $demands = $this->activeDemands($productId, $storeId, $from, $to);
            $slots = $this->slotCalculator->generateSlots($from, $to, $timezone);

            $now = Carbon::now('UTC');

            foreach ($slots as $slotStart) {
                $slotEnd = $this->slotCalculator->advance($slotStart, $timezone);

                [$demanded, $breakdown] = $this->demandForSlot($demands, $slotStart, $slotEnd);

                $this->upsertSnapshot(
                    $productId,
                    $storeId,
                    $slotStart,
                    $totalStock,
                    $demanded,
                    $breakdown,
                    $now,
                );
            }

            $this->logRecalculated($productId, $storeId, $from, $to, count($slots));
        };

        if ($usesPostgres) {
            // Serialise per product/store across concurrent recalculations. The
            // advisory lock is released automatically at transaction end.
            //
            // The lock key is a single bigint derived from product+store via
            // hashtextextended(): the two-argument int4 form
            // (pg_advisory_xact_lock(product_id, store_id)) would overflow once
            // a BIGSERIAL id exceeds ~2.1B, so we hash a composite string into
            // one int8 key instead.
            $connection->transaction(function () use ($connection, $productId, $storeId, $work): void {
                $connection->statement(
                    'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    [$productId.':'.$storeId],
                );
                $work();
            });

            return;
        }

        $work();
    }

    /**
     * Clamp a recalculation window to the rolling snapshot horizon around now:
     * `from` is pulled forward to no earlier than `now - past_days` and `to` is
     * pushed back to no later than `now + future_days`. Returns the bounded
     * half-open window; the result may be empty (from >= to) when the request
     * lies entirely outside the horizon.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function clampToHorizon(Carbon $from, Carbon $to): array
    {
        $now = Carbon::now('UTC');

        $pastDays = (int) config('availability.snapshot_horizon.past_days', 90);
        $futureDays = (int) config('availability.snapshot_horizon.future_days', 365);

        $earliest = $now->copy()->subDays(max(0, $pastDays))->startOfDay();
        $latest = $now->copy()->addDays(max(0, $futureDays))->endOfDay();

        $clampedFrom = $from->lessThan($earliest) ? $earliest->copy() : $from->copy();
        $clampedTo = $to->greaterThan($latest) ? $latest->copy() : $to->copy();

        return [$clampedFrom, $clampedTo];
    }

    /**
     * Total sellable stock for a product at a store.
     *
     * Bulk products: the sum of `quantity_held` across the store's bulk stock
     * levels (rounded to whole units — availability is integer quantities).
     * Serialised products: one unit per serialised stock-level row, so the count
     * of rows. We pick the basis by what stock rows actually exist for the
     * product/store (a product flagged serialised but with only bulk rows still
     * counts those), defaulting to the product's configured stock method.
     */
    public function totalStock(Product $product, int $storeId): int
    {
        $serialisedCount = StockLevel::query()
            ->where('product_id', $product->id)
            ->where('store_id', $storeId)
            ->where('stock_category', StockCategory::SerialisedStock)
            ->count();

        $bulkHeld = (float) StockLevel::query()
            ->where('product_id', $product->id)
            ->where('store_id', $storeId)
            ->where('stock_category', StockCategory::BulkStock)
            ->sum('quantity_held');

        // Each basis is summed independently and combined: a product may legally
        // carry both bulk and serialised rows at a store.
        return (int) round($bulkHeld) + $serialisedCount;
    }

    /**
     * Active demands for the product/store overlapping the window, eager and
     * in-memory so per-slot summing avoids N round-trips.
     *
     * @return Collection<int, Demand>
     */
    private function activeDemands(int $productId, int $storeId, Carbon $from, Carbon $to): Collection
    {
        /** @var Collection<int, Demand> $demands */
        $demands = Demand::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->overlapping($from, $to)
            ->get();

        return $demands;
    }

    /**
     * Sum the demand quantity overlapping a single `[slotStart, slotEnd)` slot
     * and build the per-source breakdown. Bulk demands are netted against any
     * `metadata.returned_quantity`.
     *
     * @param  Collection<int, Demand>  $demands
     * @return array{0: int, 1: array<string, int>}
     */
    private function demandForSlot(Collection $demands, Carbon $slotStart, Carbon $slotEnd): array
    {
        $total = 0;
        $breakdown = [];

        foreach ($demands as $demand) {
            // Half-open overlap: the demand touches the slot if it starts before
            // the slot ends and ends after the slot starts.
            if (! ($demand->starts_at->lessThan($slotEnd) && $demand->ends_at->greaterThan($slotStart))) {
                continue;
            }

            $quantity = $this->effectiveQuantity($demand);

            if ($quantity <= 0) {
                continue;
            }

            $total += $quantity;
            $breakdown[$demand->source_type] = ($breakdown[$demand->source_type] ?? 0) + $quantity;
        }

        return [$total, $breakdown];
    }

    /**
     * The demand's quantity net of partial returns. Bulk demands may carry a
     * `metadata.returned_quantity`; serialised demands are always a single unit.
     */
    private function effectiveQuantity(Demand $demand): int
    {
        $returned = (int) ($demand->metadata['returned_quantity'] ?? 0);

        return max(0, $demand->quantity - max(0, $returned));
    }

    /**
     * Upsert a single snapshot row keyed by product/store/slot.
     *
     * @param  array<string, int>  $breakdown
     */
    private function upsertSnapshot(
        int $productId,
        int $storeId,
        Carbon $slotStart,
        int $totalStock,
        int $totalDemanded,
        array $breakdown,
        Carbon $calculatedAt,
    ): void {
        AvailabilitySnapshot::query()->updateOrCreate(
            [
                'product_id' => $productId,
                'store_id' => $storeId,
                'slot_start' => $slotStart,
            ],
            [
                'total_stock' => $totalStock,
                'total_demanded' => $totalDemanded,
                'available' => $totalStock - $totalDemanded,
                'demand_breakdown' => $breakdown,
                'calculated_at' => $calculatedAt,
            ],
        );
    }

    /**
     * Append an `availability_recalculated` event for the affected range.
     */
    private function logRecalculated(int $productId, int $storeId, Carbon $from, Carbon $to, int $slotCount): void
    {
        AvailabilityEvent::query()->create([
            'event_type' => AvailabilityEventType::AvailabilityRecalculated,
            'product_id' => $productId,
            'store_id' => $storeId,
            'payload' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'slots' => $slotCount,
            ],
        ]);
    }

    /**
     * The IANA timezone for a store, falling back to the application timezone.
     */
    private function storeTimezone(int $storeId): ?string
    {
        return Store::query()->whereKey($storeId)->value('timezone');
    }
}

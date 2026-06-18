<?php

namespace App\Services\Availability;

use App\Enums\AvailabilityEventType;
use App\Enums\StockCategory;
use App\Jobs\RecalculateAvailabilityJob;
use App\Models\AvailabilityDailySummary;
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
 * The pipeline gathers stock and active demands, computes per-slot availability
 * via the {@see SlotCalculator}, upserts the affected `availability_snapshots`
 * rows, rolls up daily summaries, and appends an `availability_recalculated`
 * event. The computation itself is unchanged from M2; what changed in M3-4 is
 * the *trigger*: the {@see DemandObserver} and {@see StockLevelObserver} now
 * enqueue a debounced {@see RecalculateAvailabilityJob} that invokes
 * this pipeline on a Horizon-managed queue, rather than calling it inline.
 *
 * The pipeline accepts a bounded `(product, store, from, to)` blast radius so a
 * single change never forces a full rebuild, and remains directly callable
 * (synchronously) — the job is a thin debounced wrapper over it.
 *
 * Deferred to later milestones (intentionally NOT built here): shortage
 * detection events and webhook broadcast.
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
     *
     * Returns a {@see RecalculationResult} describing the window actually
     * materialised and the slot count, so the queued
     * {@see RecalculateAvailabilityJob} can build a broadcast summary
     * without re-querying. A skipped (no-op) run returns a zero-slot result.
     */
    public function recalculate(int $productId, int $storeId, Carbon $from, Carbon $to): RecalculationResult
    {
        $product = Product::query()->find($productId);

        if ($product === null || ! $product->track_availability) {
            return RecalculationResult::skipped($productId, $storeId);
        }

        [$from, $to] = $this->clampToHorizon($from, $to);

        // The entire requested window lies outside the rolling horizon — there
        // is nothing to materialise; point queries cover it on the fly.
        if (! $from->lessThan($to)) {
            return RecalculationResult::skipped($productId, $storeId);
        }

        $connection = (new AvailabilitySnapshot)->getConnection();
        $usesPostgres = $connection->getDriverName() === 'pgsql';

        $work = function () use ($product, $productId, $storeId, $from, $to): int {
            $timezone = $this->storeTimezone($storeId);

            $totalStock = $this->totalStock($product, $storeId);
            $demands = $this->activeDemands($productId, $storeId, $from, $to);
            $slots = $this->slotCalculator->generateSlots($from, $to, $timezone);

            $now = Carbon::now('UTC');

            // Per-slot `available` captured for the daily rollup below. Keyed by
            // the slot start (UTC) so the rollup can re-derive the local calendar
            // day each slot belongs to.
            /** @var list<array{0: Carbon, 1: int}> $slotAvailability */
            $slotAvailability = [];

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

                $slotAvailability[] = [$slotStart, $totalStock - $demanded];
            }

            $this->rollUpDailySummaries($productId, $storeId, $slotAvailability, $timezone, $now);

            $this->logRecalculated($productId, $storeId, $from, $to, count($slots));

            return count($slots);
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
            $slotCount = $connection->transaction(function () use ($connection, $productId, $storeId, $work): int {
                $connection->statement(
                    'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    [$productId.':'.$storeId],
                );

                return $work();
            });

            return new RecalculationResult($productId, $storeId, $from, $to, $slotCount);
        }

        return new RecalculationResult($productId, $storeId, $from, $to, $work());
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
     * Roll the freshly-computed slot availabilities up into daily summaries.
     *
     * Slots are grouped by the calendar day they fall on in the store's local
     * timezone (the same basis the SlotCalculator uses to align slot starts), and
     * each day's summary records the worst (`min_available`) and best
     * (`max_available`) availability across its slots, plus a `has_shortage` flag
     * when the minimum dipped below zero.
     *
     * Resolution-agnostic by design: under Daily resolution there is exactly one
     * slot per day, so the rollup is a 1:1 copy; under HalfDaily/Hourly several
     * slots collapse into the day's min/max. Populating the summary at every
     * resolution gives calendar/grid consumers one uniform read surface. Only the
     * days actually touched by this recalculation are upserted, preserving the
     * pipeline's bounded blast radius.
     *
     * @param  list<array{0: Carbon, 1: int}>  $slotAvailability  [slotStart (UTC), available]
     */
    private function rollUpDailySummaries(
        int $productId,
        int $storeId,
        array $slotAvailability,
        ?string $timezone,
        Carbon $calculatedAt,
    ): void {
        if ($slotAvailability === []) {
            return;
        }

        $tz = $this->normaliseTimezone($timezone);

        /** @var array<string, array{min: int, max: int}> $byDay */
        $byDay = [];

        foreach ($slotAvailability as [$slotStart, $available]) {
            // The local calendar day this slot belongs to. Hourly slots are pinned
            // to UTC by the SlotCalculator, but grouping by the store-local day is
            // still the correct calendar bucket for a day summary.
            $day = $slotStart->copy()->setTimezone($tz)->toDateString();

            if (! isset($byDay[$day])) {
                $byDay[$day] = ['min' => $available, 'max' => $available];

                continue;
            }

            $byDay[$day]['min'] = min($byDay[$day]['min'], $available);
            $byDay[$day]['max'] = max($byDay[$day]['max'], $available);
        }

        foreach ($byDay as $day => $bounds) {
            // Match on the day at midnight so the lookup value matches the stored,
            // `date`-cast representation (`Y-m-d 00:00:00`) — a bare `Y-m-d`
            // string would miss the existing row and force a duplicate insert.
            AvailabilityDailySummary::query()->updateOrCreate(
                [
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'date' => Carbon::parse($day)->startOfDay(),
                ],
                [
                    'min_available' => $bounds['min'],
                    'max_available' => $bounds['max'],
                    'has_shortage' => $bounds['min'] < 0,
                    'calculated_at' => $calculatedAt,
                ],
            );
        }
    }

    /**
     * Resolve the timezone to use for day-boundary grouping, defaulting to the
     * application timezone when the store has none. Mirrors
     * {@see SlotCalculator}'s normalisation so summaries bucket on the same local
     * calendar day the slots were aligned to.
     */
    private function normaliseTimezone(?string $timezone): string
    {
        if ($timezone === null || trim($timezone) === '') {
            return (string) config('app.timezone', 'UTC');
        }

        return $timezone;
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

<?php

namespace App\Services\Availability;

use App\Contracts\Availability\AvailabilityStrategyContract;
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
use App\Services\Shortages\PipelineShortageEmitter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;
use Thunk\Verbs\Facades\Verbs;

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
        private readonly AvailabilityStrategyContract $strategy,
        private readonly PipelineShortageEmitter $shortageEmitter,
    ) {}

    /**
     * Recalculate snapshots for the product/store over the half-open window
     * `[$from, $to)`. No-op for products that do not track availability.
     *
     * The window is first clamped to the rolling snapshot horizon (settings
     * `availability.snapshot_horizon_past_days` / `_future_days`) so an
     * indefinite/sentinel-dated demand
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
     * The advisory-locked transaction wrapper is only opened when this method is
     * called OUTSIDE any existing transaction (`transactionLevel() === 0`). When a
     * caller already owns a transaction — a wrapping action, or the pgsql test
     * harness which wraps each test in `beginTransaction()` — the work runs
     * directly: the outer transaction already isolates the upserts, and opening a
     * nested savepoint plus a `pg_advisory_xact_lock` would be both redundant and
     * harmful. A transaction-scoped advisory lock taken inside an outer
     * transaction is not released until that OUTER transaction commits, so it
     * would linger and contend (it deadlocks/hangs the pgsql test lane). Letting
     * the caller own the transaction avoids both the nested savepoint and the
     * lingering lock.
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

        // The closure returns [slotCount, hasShortage]: hasShortage is true when
        // any materialised slot dipped below zero availability.
        $work = function () use ($product, $productId, $storeId, $from, $to): array {
            $timezone = $this->storeTimezone($storeId);

            $totalStock = $this->totalStock($product, $storeId);
            $demands = $this->activeDemands($productId, $storeId, $from, $to);

            // Step 3 — pre-calculation hook. The strategy may add synthetic
            // demands, drop demands, or adjust quantities before per-slot summing.
            // The OSS default returns the set unchanged.
            $demands = $this->strategy->preCalculation($productId, $storeId, $from, $to, $demands);

            $slots = $this->slotCalculator->generateSlots($from, $to, $timezone);

            $now = Carbon::now('UTC');

            // Compute each slot's availability into an ordered result set first, so
            // the post-calculation hook can adjust the final numbers before any
            // snapshot is written.
            /** @var SupportCollection<int, array{slot_start: Carbon, total_stock: int, total_demanded: int, available: int, breakdown: array<string, int>}> $slotResults */
            $slotResults = new SupportCollection;

            foreach ($slots as $slotStart) {
                $slotEnd = $this->slotCalculator->advance($slotStart, $timezone);

                [$demanded, $breakdown] = $this->demandForSlot($demands, $slotStart, $slotEnd);

                $slotResults->push([
                    'slot_start' => $slotStart,
                    'total_stock' => $totalStock,
                    'total_demanded' => $demanded,
                    'available' => $totalStock - $demanded,
                    'breakdown' => $breakdown,
                ]);
            }

            // Step 5 — post-calculation hook. The strategy may clamp/floor the
            // final per-slot numbers (buffer-stock minimums, caps). The OSS
            // default returns the results unchanged.
            $slotResults = $this->strategy->postCalculation($productId, $storeId, $from, $to, $slotResults);

            // Previous availability per slot (keyed by UTC slot-start ISO) so we
            // can detect which slots CROSS into or out of negative availability on
            // this recalc — step 7/8 shortage emission below.
            $previousAvailability = $this->previousAvailability($productId, $storeId, $slotResults);

            // Per-slot `available` captured for the daily rollup below. Keyed by
            // the slot start (UTC) so the rollup can re-derive the local calendar
            // day each slot belongs to.
            /** @var list<array{0: Carbon, 1: int}> $slotAvailability */
            $slotAvailability = [];

            $hasShortage = false;
            $slotCount = 0;

            // Did ANY slot newly dip below zero / newly recover this recalc?
            $crossedIntoShortage = false;
            $crossedOutOfShortage = false;

            foreach ($slotResults as $result) {
                $this->upsertSnapshot(
                    $productId,
                    $storeId,
                    $result['slot_start'],
                    $result['total_stock'],
                    $result['total_demanded'],
                    $result['available'],
                    $result['breakdown'],
                    $now,
                );

                $slotAvailability[] = [$result['slot_start'], $result['available']];

                // A slot with no prior snapshot is treated as previously
                // non-negative (zero stock pressure) so a brand-new negative slot
                // counts as a crossing INTO shortage.
                $key = $result['slot_start']->copy()->utc()->toIso8601String();
                $previous = $previousAvailability[$key] ?? 0;
                $current = $result['available'];

                if ($previous >= 0 && $current < 0) {
                    $crossedIntoShortage = true;
                } elseif ($previous < 0 && $current >= 0) {
                    $crossedOutOfShortage = true;
                }

                if ($current < 0) {
                    $hasShortage = true;
                }

                $slotCount++;
            }

            $this->rollUpDailySummaries($productId, $storeId, $slotAvailability, $timezone, $now);

            $this->logRecalculated($productId, $storeId, $from, $to, $slotCount);

            // Steps 7/8 — fire product/store shortage events when a slot crossed a
            // zero-availability boundary on this recalc (stock-write-induced
            // shortages, not just opportunity-confirmation ones). This is the
            // AUTHORITATIVE product/store emission point; the AvailabilityChanged
            // listener emits at opportunity-item granularity instead, so the two
            // never double-fire (see PipelineShortageEmitter). Skipped on replay.
            if (! Verbs::isReplaying() && ($crossedIntoShortage || $crossedOutOfShortage)) {
                $this->shortageEmitter->emitForRecalc(
                    $productId,
                    $storeId,
                    $from,
                    $to,
                    $crossedIntoShortage,
                    $crossedOutOfShortage,
                );
            }

            return [$slotCount, $hasShortage];
        };

        // Only open the advisory-locked transaction wrapper at the top level. If a
        // caller already owns a transaction the work runs directly: the outer
        // transaction isolates the upserts, and a nested savepoint + a
        // transaction-scoped advisory lock would be redundant and would linger
        // until the OUTER transaction commits (contention/deadlock).
        if ($usesPostgres && $connection->transactionLevel() === 0) {
            // Serialise per product/store across concurrent recalculations. The
            // advisory lock is released automatically at transaction end.
            //
            // The lock key is a single bigint derived from product+store via
            // hashtextextended(): the two-argument int4 form
            // (pg_advisory_xact_lock(product_id, store_id)) would overflow once
            // a BIGSERIAL id exceeds ~2.1B, so we hash a composite string into
            // one int8 key instead.
            //
            // The advisory wait is bounded by `availability.recalculation_lock_timeout_ms`
            // via `SET LOCAL lock_timeout`, so a contended product/store never
            // blocks a worker indefinitely. On timeout Postgres raises SQLSTATE
            // 55P03 (lock_not_available); we catch it, skip this run, and log —
            // the recalc is idempotent and a later trigger (or the next debounced
            // job) re-materialises the window. We do NOT rethrow: rethrowing would
            // fail the queued job and churn retries against the same contention.
            try {
                /** @var array{0: int, 1: bool} $outcome */
                $outcome = $connection->transaction(function () use ($connection, $productId, $storeId, $work): array {
                    $connection->statement(
                        'SET LOCAL lock_timeout = '.$this->lockTimeoutMs(),
                    );

                    $connection->statement(
                        'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                        [$productId.':'.$storeId],
                    );

                    return $work();
                });
            } catch (QueryException $exception) {
                if ($this->isLockTimeout($exception)) {
                    Log::warning('availability.recalculation lock timeout — skipping run', [
                        'product_id' => $productId,
                        'store_id' => $storeId,
                        'timeout_ms' => $this->lockTimeoutMs(),
                    ]);

                    return RecalculationResult::skipped($productId, $storeId);
                }

                throw $exception;
            }

            return new RecalculationResult($productId, $storeId, $from, $to, $outcome[0], $outcome[1]);
        }

        [$slotCount, $hasShortage] = $work();

        return new RecalculationResult($productId, $storeId, $from, $to, $slotCount, $hasShortage);
    }

    /**
     * The bounded wait (milliseconds) for the per-product/store advisory lock,
     * read from the `availability.recalculation_lock_timeout_ms` setting with the
     * settings-default fallback. Clamped to a non-negative integer so it is safe
     * to inline into the `SET LOCAL lock_timeout` statement. A value of `0` means
     * "wait indefinitely" in Postgres — the setting's own bounds keep it positive
     * by default.
     */
    private function lockTimeoutMs(): int
    {
        return max(0, (int) settings('availability.recalculation_lock_timeout_ms', 5000));
    }

    /**
     * Whether a {@see QueryException} is a Postgres `lock_timeout` failure
     * (SQLSTATE `55P03`, lock_not_available) — the error raised when
     * `pg_advisory_xact_lock` waits past `lock_timeout`. Matched on the SQLSTATE
     * rather than the message so it is locale-independent.
     */
    private function isLockTimeout(QueryException $exception): bool
    {
        return $exception->getCode() === '55P03';
    }

    /**
     * Clamp a recalculation window to the rolling snapshot horizon around now:
     * `from` is pulled forward to no earlier than `now - past_days` and `to` is
     * pushed back to no later than `now + future_days`. Returns the bounded
     * half-open window; the result may be empty (from >= to) when the request
     * lies entirely outside the horizon.
     *
     * Public so the live read path ({@see AvailabilityService}) clamps its
     * slot-generation windows to the SAME rolling horizon — an indefinite
     * (sentinel-dated) demand otherwise spans tens of thousands of slots and trips
     * the {@see SlotCalculator} safety cap.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function clampToHorizon(Carbon $from, Carbon $to): array
    {
        $now = Carbon::now('UTC');

        $pastDays = (int) settings('availability.snapshot_horizon_past_days', 90);
        $futureDays = (int) settings('availability.snapshot_horizon_future_days', 365);

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
     * Overlap is tested against the demand's BUFFERED bounds (turnaround/prep
     * baked in) — the same window the fetch overlaps on — so snapshots and daily
     * summaries reflect a unit being occupied through its prep/turnaround slots,
     * matching the Postgres `period &&` fetch on every driver.
     *
     * Accepts the base {@see SupportCollection} so the (possibly strategy-adjusted)
     * demand set from {@see AvailabilityStrategyContract::preCalculation()} — which
     * may no longer be an {@see Collection} (Eloquent) instance — is summed
     * uniformly.
     *
     * @param  SupportCollection<int, Demand>  $demands
     * @return array{0: int, 1: array<string, int>}
     */
    private function demandForSlot(SupportCollection $demands, Carbon $slotStart, Carbon $slotEnd): array
    {
        $total = 0;
        $breakdown = [];

        foreach ($demands as $demand) {
            // Half-open overlap: the demand touches the slot if its buffered
            // window starts before the slot ends and ends after the slot starts.
            if (! ($demand->bufferedStartsAt()->lessThan($slotEnd) && $demand->bufferedEndsAt()->greaterThan($slotStart))) {
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
        int $available,
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
                // `available` is honoured as supplied so a post-calculation
                // strategy hook's adjustment (buffer-stock floor, cap) is
                // persisted, rather than re-derived from stock minus demand.
                'available' => $available,
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

    /**
     * Read the currently-stored availability for the slots about to be upserted,
     * keyed by UTC slot-start ISO string, so the recalc can detect zero-crossings.
     * Slots with no existing snapshot are simply absent from the map (callers
     * treat absence as "previously non-negative").
     *
     * @param  SupportCollection<int, array{slot_start: Carbon, total_stock: int, total_demanded: int, available: int, breakdown: array<string, int>}>  $slotResults
     * @return array<string, int>
     */
    private function previousAvailability(int $productId, int $storeId, SupportCollection $slotResults): array
    {
        if ($slotResults->isEmpty()) {
            return [];
        }

        $slotStarts = $slotResults
            ->map(static fn (array $result): Carbon => $result['slot_start'])
            ->all();

        /** @var array<string, int> $map */
        $map = [];

        AvailabilitySnapshot::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->whereIn('slot_start', $slotStarts)
            ->get(['slot_start', 'available'])
            ->each(function (AvailabilitySnapshot $snapshot) use (&$map): void {
                $key = $snapshot->slot_start->copy()->utc()->toIso8601String();
                $map[$key] = (int) $snapshot->available;
            });

        return $map;
    }
}

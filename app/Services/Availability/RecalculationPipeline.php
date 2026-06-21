<?php

namespace App\Services\Availability;

use App\Contracts\Availability\AvailabilityStrategyContract;
use App\Enums\AvailabilityEventType;
use App\Enums\DemandPhase;
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
     * Per-instance cache of store timezones, keyed by store id. A store's
     * timezone is static within a request/job, so this avoids the repeated
     * single-column SELECT every point-read / recalc otherwise fires.
     *
     * @var array<int, string|null>
     */
    private array $storeTimezones = [];

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

            // Pending check-in demands are Closed (inactive) and so are absent
            // from the active set above; they never affect availability. They are
            // fetched separately purely so each slot can report an informational
            // `pending_checkin_quantity` and a `returned_not_checked` breakdown
            // entry (availability-engine.md §"Pending check-in visibility").
            $pendingCheckinDemands = $this->pendingCheckinDemands($productId, $storeId, $from, $to);

            // Step 3 — pre-calculation hook. The strategy may add synthetic
            // demands, drop demands, or adjust quantities before per-slot summing.
            // The OSS default returns the set unchanged.
            $demands = $this->strategy->preCalculation($productId, $storeId, $from, $to, $demands);

            $slots = $this->slotCalculator->generateSlots($from, $to, $timezone);

            $now = Carbon::now('UTC');

            // Compute each slot's availability into an ordered result set first, so
            // the post-calculation hook can adjust the final numbers before any
            // snapshot is written. `$pendingCheckinBySlot` (keyed by UTC slot-start
            // ISO) is computed alongside but kept OUT of the strategy collection so
            // the strategy contract's slot shape stays unchanged; it is looked up at
            // upsert time and is informational only (never affects `available`).
            [$slotResults, $pendingCheckinBySlot] = $this->processSlots(
                $slots,
                $timezone,
                $totalStock,
                $demands,
                $pendingCheckinDemands,
            );

            // Step 5 — post-calculation hook. The strategy may clamp/floor the
            // final per-slot numbers (buffer-stock minimums, caps). The OSS
            // default returns the results unchanged.
            $slotResults = $this->strategy->postCalculation($productId, $storeId, $from, $to, $slotResults);

            // Previous availability per slot (keyed by UTC slot-start ISO) so we
            // can detect which slots CROSS into or out of negative availability on
            // this recalc — step 7/8 shortage emission below.
            $previousAvailability = $this->previousAvailability($productId, $storeId, $slotResults);

            $applied = $this->applySlotResults(
                $productId,
                $storeId,
                $slotResults,
                $pendingCheckinBySlot,
                $previousAvailability,
                $timezone,
                $now,
            );

            $hasShortage = $applied['has_shortage'];
            $slotCount = $applied['slot_count'];
            $crossedIntoShortage = $applied['crossed_into_shortage'];
            $crossedOutOfShortage = $applied['crossed_out_of_shortage'];

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
     * The full rolling snapshot horizon around now as a half-open window:
     * `[now - past_days (start of day), now + future_days (end of day)]`, reading
     * the `availability.snapshot_horizon_past_days` / `_future_days` settings once.
     *
     * Shared by the maintenance/recompute jobs ({@see RecalculateAvailabilityJob},
     * {@see RebuildSnapshotsJob}, {@see VerifySnapshotIntegrity}) so the default
     * rebuild window is defined in exactly one place and matches the bounds
     * {@see clampToHorizon()} enforces.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function fullHorizon(): array
    {
        $now = Carbon::now('UTC');

        $pastDays = (int) settings('availability.snapshot_horizon_past_days', 90);
        $futureDays = (int) settings('availability.snapshot_horizon_future_days', 365);

        $from = $now->copy()->subDays(max(0, $pastDays))->startOfDay();
        $to = $now->copy()->addDays(max(0, $futureDays))->endOfDay();

        return [$from, $to];
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
        // One grouped pass over the product/store stock rows: per stock category
        // we read both the row count (serialised basis) and the summed
        // quantity_held (bulk basis). The serialised basis takes the COUNT of
        // serialised rows; the bulk basis takes the rounded SUM of bulk rows.
        //
        // The discriminator is aliased to `category` (a plain int, not the model's
        // enum-cast `stock_category`) so it is compared against the enum's backing
        // values without relying on the cast surviving an aggregate select.
        /** @var Collection<int, StockLevel> $rows */
        $rows = StockLevel::query()
            ->where('product_id', $product->id)
            ->where('store_id', $storeId)
            ->selectRaw('stock_category as category, COUNT(*) as cnt, COALESCE(SUM(quantity_held), 0) as held')
            ->groupBy('stock_category')
            ->get();

        $serialisedCount = 0;
        $bulkHeld = 0.0;

        foreach ($rows as $row) {
            $category = (int) $row->getAttributeValue('category');

            if ($category === StockCategory::SerialisedStock->value) {
                $serialisedCount += (int) $row->getAttributeValue('cnt');
            } elseif ($category === StockCategory::BulkStock->value) {
                $bulkHeld += (float) $row->getAttributeValue('held');
            }
        }

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
     * Sum the effective demand quantity overlapping a single `[slotStart, slotEnd)`
     * slot and build the per-source breakdown. Each demand contributes its
     * {@see Demand::effectiveQuantity()} (quantity net of any partial return);
     * zero-quantity demands are skipped so they never appear in the breakdown.
     *
     * Overlap is tested against the demand's BUFFERED bounds (turnaround/prep
     * baked in) — the same window the fetch overlaps on — so snapshots, daily
     * summaries and the live reads all reflect a unit being occupied through its
     * prep/turnaround slots, matching the Postgres `period &&` fetch on every
     * driver.
     *
     * Accepts any `iterable` of demands so the recalculation build (a possibly
     * strategy-adjusted {@see SupportCollection}) and the live reads (an Eloquent
     * {@see Collection}) share one summing definition. This is the single
     * authoritative per-slot demand sum for the whole availability engine.
     *
     * @param  iterable<int, Demand>  $demands
     * @return array{0: int, 1: array<string, int>}
     */
    public function sumDemandForSlot(iterable $demands, Carbon $slotStart, Carbon $slotEnd): array
    {
        $total = 0;
        $breakdown = [];

        foreach ($demands as $demand) {
            // Half-open overlap: the demand touches the slot if its buffered
            // window starts before the slot ends and ends after the slot starts.
            if (! ($demand->bufferedStartsAt()->lessThan($slotEnd) && $demand->bufferedEndsAt()->greaterThan($slotStart))) {
                continue;
            }

            $quantity = $demand->effectiveQuantity();

            if ($quantity <= 0) {
                continue;
            }

            $total += $quantity;
            $breakdown[$demand->source_type] = ($breakdown[$demand->source_type] ?? 0) + $quantity;
        }

        return [$total, $breakdown];
    }

    /**
     * Compute each slot's availability into an ordered result set, plus the
     * per-slot pending check-in queue (kept out of the result set so the strategy
     * contract's slot shape is unchanged). Pure: it performs no I/O — fetches
     * happen in the caller — and produces exactly the values the previous inline
     * loop did, so the post-calculation hook and downstream writes are unaffected.
     *
     * @param  iterable<int, Carbon>  $slots
     * @param  iterable<int, Demand>  $demands
     * @param  Collection<int, Demand>  $pendingCheckinDemands
     * @return array{0: SupportCollection<int, array{slot_start: Carbon, total_stock: int, total_demanded: int, available: int, breakdown: array<string, int>}>, 1: array<string, int>}
     */
    private function processSlots(
        iterable $slots,
        ?string $timezone,
        int $totalStock,
        iterable $demands,
        Collection $pendingCheckinDemands,
    ): array {
        /** @var SupportCollection<int, array{slot_start: Carbon, total_stock: int, total_demanded: int, available: int, breakdown: array<string, int>}> $slotResults */
        $slotResults = new SupportCollection;

        /** @var array<string, int> $pendingCheckinBySlot */
        $pendingCheckinBySlot = [];

        foreach ($slots as $slotStart) {
            $slotEnd = $this->slotCalculator->advance($slotStart, $timezone);

            [$demanded, $breakdown] = $this->sumDemandForSlot($demands, $slotStart, $slotEnd);

            $pendingCheckin = $this->pendingCheckinForSlot($pendingCheckinDemands, $slotStart, $slotEnd);

            if ($pendingCheckin > 0) {
                // Fold the check-in queue into the breakdown under the
                // `returned_not_checked` key (availability-engine.md
                // §"Pending check-in visibility") so a dashboard widget can
                // read it from the snapshot's demand_breakdown too.
                $breakdown['returned_not_checked'] = $pendingCheckin;
                $pendingCheckinBySlot[$slotStart->copy()->utc()->toIso8601String()] = $pendingCheckin;
            }

            $slotResults->push([
                'slot_start' => $slotStart,
                'total_stock' => $totalStock,
                'total_demanded' => $demanded,
                'available' => $totalStock - $demanded,
                'breakdown' => $breakdown,
            ]);
        }

        return [$slotResults, $pendingCheckinBySlot];
    }

    /**
     * Persist the (post-hook) slot results: batch-upsert the snapshot rows, roll
     * the affected days up into daily summaries, and report the slot count,
     * shortage flag, and the zero-crossing flags the shortage emitter needs.
     *
     * The writes are identical in content to the previous per-slot
     * `updateOrCreate` loop — only batched — so idempotency and replay-safety are
     * preserved. The slot-result ITERATION ORDER is unchanged, so the crossing
     * detection and `slotAvailability` rollup ordering match exactly.
     *
     * @param  SupportCollection<int, array{slot_start: Carbon, total_stock: int, total_demanded: int, available: int, breakdown: array<string, int>}>  $slotResults
     * @param  array<string, int>  $pendingCheckinBySlot
     * @param  array<string, int>  $previousAvailability
     * @return array{has_shortage: bool, slot_count: int, crossed_into_shortage: bool, crossed_out_of_shortage: bool}
     */
    private function applySlotResults(
        int $productId,
        int $storeId,
        SupportCollection $slotResults,
        array $pendingCheckinBySlot,
        array $previousAvailability,
        ?string $timezone,
        Carbon $now,
    ): array {
        // Per-slot `available` (and `pending_checkin`) captured for the daily
        // rollup below. Keyed by the slot start (UTC) so the rollup can re-derive
        // the local calendar day each slot belongs to.
        /** @var list<array{0: Carbon, 1: int, 2: int}> $slotAvailability */
        $slotAvailability = [];

        // Accumulated snapshot rows, upserted in one batched statement after the
        // loop (chunked to stay under Postgres' bind-parameter ceiling).
        /** @var list<array<string, mixed>> $snapshotRows */
        $snapshotRows = [];

        $hasShortage = false;
        $slotCount = 0;

        // Did ANY slot newly dip below zero / newly recover this recalc?
        $crossedIntoShortage = false;
        $crossedOutOfShortage = false;

        foreach ($slotResults as $result) {
            $slotPendingCheckin = $pendingCheckinBySlot[$result['slot_start']->copy()->utc()->toIso8601String()] ?? 0;

            $snapshotRows[] = $this->snapshotRow(
                $productId,
                $storeId,
                $result['slot_start'],
                $result['total_stock'],
                $result['total_demanded'],
                $result['available'],
                $result['breakdown'],
                $slotPendingCheckin,
                $now,
            );

            $slotAvailability[] = [$result['slot_start'], $result['available'], $slotPendingCheckin];

            // A slot with no prior snapshot is treated as previously non-negative
            // (zero stock pressure) so a brand-new negative slot counts as a
            // crossing INTO shortage.
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

        $this->batchUpsertSnapshots($snapshotRows);

        $this->rollUpDailySummaries($productId, $storeId, $slotAvailability, $timezone, $now);

        return [
            'has_shortage' => $hasShortage,
            'slot_count' => $slotCount,
            'crossed_into_shortage' => $crossedIntoShortage,
            'crossed_out_of_shortage' => $crossedOutOfShortage,
        ];
    }

    /**
     * Build one snapshot upsert row keyed by product/store/slot.
     *
     * The values are run through a transient model so they are cast/serialised
     * exactly as {@see AvailabilitySnapshot::save()} would write them (Carbon →
     * datetime string, breakdown array → JSON, ints as-is). `upsert()` does NOT
     * apply casts to its value rows, so pre-casting here keeps the stored bytes
     * identical to the previous `updateOrCreate` path.
     *
     * @param  array<string, int>  $breakdown
     * @return array<string, mixed>
     */
    private function snapshotRow(
        int $productId,
        int $storeId,
        Carbon $slotStart,
        int $totalStock,
        int $totalDemanded,
        int $available,
        array $breakdown,
        int $pendingCheckin,
        Carbon $calculatedAt,
    ): array {
        return (new AvailabilitySnapshot)->forceFill([
            'product_id' => $productId,
            'store_id' => $storeId,
            'slot_start' => $slotStart,
            'total_stock' => $totalStock,
            'total_demanded' => $totalDemanded,
            // `available` is honoured as supplied so a post-calculation strategy
            // hook's adjustment (buffer-stock floor, cap) is persisted, rather than
            // re-derived from stock minus demand.
            'available' => $available,
            'demand_breakdown' => $breakdown,
            // Informational check-in queue size for this slot (returned but not yet
            // inspected) — does not affect `available`.
            'pending_checkin_quantity' => $pendingCheckin,
            'calculated_at' => $calculatedAt,
        ])->getAttributes();
    }

    /**
     * Batch-upsert the accumulated snapshot rows in one statement per chunk.
     *
     * Conflicts are resolved on the `(product_id, store_id, slot_start)` unique
     * index (`uq_snapshots_product_store_slot`), updating exactly the columns the
     * previous per-row `updateOrCreate` updated — never the unique keys and never
     * `created_at` (`upsert()` adds `updated_at` to the update set automatically).
     *
     * Chunked at 1,000 rows so a wide horizon stays well under Postgres'
     * bind-parameter ceiling (~11 columns × 1,000 = ~11k binds per statement).
     * `upsert()` fires no model events; the previous `updateOrCreate` path had no
     * observers/broadcasts bound to AvailabilitySnapshot, so none are lost.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function batchUpsertSnapshots(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            AvailabilitySnapshot::query()->upsert(
                $chunk,
                ['product_id', 'store_id', 'slot_start'],
                [
                    'total_stock',
                    'total_demanded',
                    'available',
                    'demand_breakdown',
                    'pending_checkin_quantity',
                    'calculated_at',
                ],
            );
        }
    }

    /**
     * The pending check-in demands for the product/store overlapping the window:
     * Closed-phase (inactive) demands whose source marked the unit physically
     * returned but not yet inspected (`metadata.pending_checkin = true`). Fetched
     * once and summed per slot in-memory, mirroring {@see activeDemands()}.
     *
     * These never reduce availability — they are surfaced purely so the snapshot
     * can carry an informational check-in-queue count
     * (availability-engine.md §"Pending check-in visibility").
     *
     * @return Collection<int, Demand>
     */
    private function pendingCheckinDemands(int $productId, int $storeId, Carbon $from, Carbon $to): Collection
    {
        /** @var Collection<int, Demand> $demands */
        $demands = Demand::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->where('phase', DemandPhase::Closed)
            ->where('metadata->pending_checkin', true)
            ->overlapping($from, $to)
            ->get();

        return $demands;
    }

    /**
     * Sum the pending check-in quantity overlapping a single
     * `[slotStart, slotEnd)` slot. Uses the demand's buffered bounds so the
     * count agrees with the same overlap test the active-demand summing uses.
     *
     * @param  Collection<int, Demand>  $demands
     */
    private function pendingCheckinForSlot(Collection $demands, Carbon $slotStart, Carbon $slotEnd): int
    {
        $total = 0;

        foreach ($demands as $demand) {
            if (! ($demand->bufferedStartsAt()->lessThan($slotEnd) && $demand->bufferedEndsAt()->greaterThan($slotStart))) {
                continue;
            }

            $total += max(0, $demand->quantity);
        }

        return $total;
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
     * `pending_checkin_quantity` rolls up as the day's PEAK (max) per-slot value:
     * the queue of returned-not-checked units within a day reflects the same
     * physical returns across overlapping slots, so summing would double-count —
     * the worst moment of the day is the meaningful figure (mirroring how
     * `max_available` is the best slot). It is informational only and never feeds
     * `min_available` / `max_available` / `has_shortage`.
     *
     * Resolution-agnostic by design: under Daily resolution there is exactly one
     * slot per day, so the rollup is a 1:1 copy; under HalfDaily/Hourly several
     * slots collapse into the day's min/max. Populating the summary at every
     * resolution gives calendar/grid consumers one uniform read surface. Only the
     * days actually touched by this recalculation are upserted, preserving the
     * pipeline's bounded blast radius.
     *
     * @param  list<array{0: Carbon, 1: int, 2: int}>  $slotAvailability  [slotStart (UTC), available, pendingCheckin]
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

        $tz = $this->slotCalculator->normaliseTimezone($timezone);

        /** @var array<string, array{min: int, max: int, pending_checkin: int}> $byDay */
        $byDay = [];

        foreach ($slotAvailability as [$slotStart, $available, $pendingCheckin]) {
            // The local calendar day this slot belongs to. Hourly slots are pinned
            // to UTC by the SlotCalculator, but grouping by the store-local day is
            // still the correct calendar bucket for a day summary.
            $day = $slotStart->copy()->setTimezone($tz)->toDateString();

            if (! isset($byDay[$day])) {
                $byDay[$day] = ['min' => $available, 'max' => $available, 'pending_checkin' => $pendingCheckin];

                continue;
            }

            $byDay[$day]['min'] = min($byDay[$day]['min'], $available);
            $byDay[$day]['max'] = max($byDay[$day]['max'], $available);
            $byDay[$day]['pending_checkin'] = max($byDay[$day]['pending_checkin'], $pendingCheckin);
        }

        // Build all touched-day rows, cast/serialised through a transient model so
        // the stored bytes match the previous `updateOrCreate` path exactly, then
        // upsert them in one statement. The day is pinned to midnight so the cast
        // `date` value matches the stored `Y-m-d 00:00:00` representation — a bare
        // `Y-m-d` string would miss the existing row and force a duplicate insert.
        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($byDay as $day => $bounds) {
            $rows[] = (new AvailabilityDailySummary)->forceFill([
                'product_id' => $productId,
                'store_id' => $storeId,
                'date' => Carbon::parse($day)->startOfDay(),
                'min_available' => $bounds['min'],
                'max_available' => $bounds['max'],
                'pending_checkin_quantity' => $bounds['pending_checkin'],
                'has_shortage' => $bounds['min'] < 0,
                'calculated_at' => $calculatedAt,
            ])->getAttributes();
        }

        $this->batchUpsertDailySummaries($rows);
    }

    /**
     * Batch-upsert the accumulated daily-summary rows in one statement per chunk.
     *
     * Conflicts are resolved on the `(product_id, store_id, date)` unique index
     * (`uq_daily_summaries_product_store_date`), updating exactly the columns the
     * previous per-day `updateOrCreate` updated — never the unique keys and never
     * `created_at` (`upsert()` adds `updated_at` automatically).
     *
     * Chunked at 1,000 rows (one row per touched calendar day; a recalc window
     * rarely approaches that, but the chunk keeps the bind count bounded regardless).
     * `upsert()` fires no model events; the previous path had no observers/broadcasts
     * bound to AvailabilityDailySummary, so none are lost.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function batchUpsertDailySummaries(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            AvailabilityDailySummary::query()->upsert(
                $chunk,
                ['product_id', 'store_id', 'date'],
                [
                    'min_available',
                    'max_available',
                    'pending_checkin_quantity',
                    'has_shortage',
                    'calculated_at',
                ],
            );
        }
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
     * The IANA timezone for a store (or null when the store has none / does not
     * exist), memoised per instance.
     *
     * Public so the read path ({@see AvailabilityService}) shares one definition
     * and one cache rather than re-querying `stores.timezone` on every point read.
     */
    public function storeTimezone(int $storeId): ?string
    {
        return $this->storeTimezones[$storeId] ??= Store::query()->whereKey($storeId)->value('timezone');
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

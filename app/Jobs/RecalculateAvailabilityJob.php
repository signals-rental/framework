<?php

namespace App\Jobs;

use App\Events\Availability\AvailabilityChanged;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Debounced, asynchronous availability recompute for a single product/store.
 *
 * The demand and stock observers no longer recompute snapshots inline; they
 * enqueue this job, which runs the {@see RecalculationPipeline} over the rolling
 * snapshot horizon for the carried product/store and then broadcasts an
 * {@see AvailabilityChanged} Reverb event so live calendar/grid views refresh.
 *
 * **Debouncing.** The job is {@see ShouldBeUnique}: its {@see uniqueId()} is
 * `availability:{productId}:{storeId}` and the lock is held for
 * {@see uniqueFor()} seconds. A burst of demand/stock changes for the same
 * product/store therefore coalesces into a single queued recompute instead of
 * one per write — the pipeline reads the *current* demand/stock state when it
 * eventually runs, so a single late run reflects the whole burst.
 *
 * **Idempotent.** The pipeline upserts snapshots keyed by product/store/slot and
 * rolls daily summaries with `updateOrCreate`, so re-running the job (a retry, or
 * a second dispatch after the lock clears) converges to the same result.
 *
 * **Replay-safe.** This job is dispatched only by Eloquent observers on real
 * demand/stock writes. During a `Verbs::replay()` no demand rows are written
 * (sync is `Verbs::unlessReplaying()`-guarded) and the observers additionally
 * short-circuit on replay, so neither the recompute nor the broadcast happens
 * while rebuilding the event store.
 *
 * Point availability ({@see AvailabilityService::getAvailability()})
 * is unaffected by the async switch: it reads the authoritative `demands` table
 * live and never depends on snapshots, so it stays exact. Only the
 * snapshot/range/calendar read model is eventually-consistent.
 */
class RecalculateAvailabilityJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Recompute is cheap and idempotent; a single attempt is enough. A failure
     * is re-triggered by the next demand/stock change or the next overdue sweep.
     */
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $productId,
        public int $storeId,
    ) {
        $this->onQueue((string) config('availability.recalc.queue', 'availability'));
    }

    /**
     * Coalesce concurrent dispatches for the same product/store onto one job.
     */
    public function uniqueId(): string
    {
        return 'availability:'.$this->productId.':'.$this->storeId;
    }

    /**
     * How long (seconds) the uniqueness lock is held — the debounce window.
     */
    public function uniqueFor(): int
    {
        return max(1, (int) config('availability.recalc.debounce_seconds', 2));
    }

    /**
     * Prevent two recomputes for the SAME product/store from running
     * concurrently (a debounced dispatch can clear the {@see ShouldBeUnique} lock
     * and enqueue a second job while the first is still mid-recalc).
     * {@see WithoutOverlapping} keyed on `product_id:store_id` serialises them;
     * `dontRelease()` drops the colliding attempt rather than re-queueing it,
     * since the running job already reads the current demand/stock state. The
     * Postgres advisory lock guards cross-process interleaving at the DB layer;
     * this guards the worker layer.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->productId.':'.$this->storeId))
                ->dontRelease()
                ->expireAfter($this->timeout + 60),
        ];
    }

    /**
     * Recalculate the rolling snapshot horizon for the product/store, then
     * broadcast the change. No-ops (untracked product, empty horizon) skip the
     * broadcast.
     */
    public function handle(RecalculationPipeline $pipeline): void
    {
        $now = Carbon::now('UTC');

        $pastDays = (int) settings('availability.snapshot_horizon_past_days', 90);
        $futureDays = (int) settings('availability.snapshot_horizon_future_days', 365);

        $from = $now->copy()->subDays(max(0, $pastDays))->startOfDay();
        $to = $now->copy()->addDays(max(0, $futureDays))->endOfDay();

        $result = $pipeline->recalculate($this->productId, $this->storeId, $from, $to);

        if (! $result->recalculated()) {
            return;
        }

        AvailabilityChanged::dispatch(
            $this->productId,
            $this->storeId,
            $result->from?->toIso8601String(),
            $result->to?->toIso8601String(),
            $result->slots,
            $result->hasShortage,
        );
    }
}

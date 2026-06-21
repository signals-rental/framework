<?php

namespace App\Jobs;

use App\Services\Availability\RecalculationPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Full snapshot rebuild for one product/store over an explicit window.
 *
 * Unlike {@see RecalculateAvailabilityJob} (debounced, fixed to the rolling
 * horizon, fired by observers), this is an on-demand maintenance rebuild for a
 * caller-chosen window — backfilling a newly tracked product, repairing a known
 * range, or re-materialising after a bulk import. It runs the
 * {@see RecalculationPipeline} directly, which clamps the window to the rolling
 * horizon and upserts snapshots/daily summaries idempotently.
 *
 * **Idempotent.** The pipeline upserts keyed on product/store/slot, so a retry or
 * a re-dispatch converges to the same materialised state.
 *
 * **Replay-safe.** Dispatched only from operator/maintenance entry points, never
 * during a `Verbs::replay()`. It reads — never writes — demand rows, so it cannot
 * perturb the event store. (It does not fire demand events, so there is nothing
 * to skip during replay.)
 *
 * Parallelism: callers rebuilding many products dispatch one job per
 * product/store (optionally via `Bus::batch()`); {@see WithoutOverlapping} keyed
 * on `product_id:store_id` serialises concurrent rebuilds of the same pair.
 */
class RebuildSnapshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  string|null  $from  ISO-8601 window start; null = rolling horizon start
     * @param  string|null  $to  ISO-8601 window end; null = rolling horizon end
     */
    public function __construct(
        public int $productId,
        public int $storeId,
        public ?string $from = null,
        public ?string $to = null,
    ) {
        $this->onQueue((string) config('availability.recalc.queue', 'availability'));
    }

    /**
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

    public function handle(RecalculationPipeline $pipeline): void
    {
        // Default to the full rolling horizon; an explicit window is clamped to it
        // by the pipeline so an out-of-horizon request cannot materialise an
        // unbounded number of slots.
        [$horizonFrom, $horizonTo] = $pipeline->fullHorizon();

        $from = $this->from !== null ? Carbon::parse($this->from) : $horizonFrom;
        $to = $this->to !== null ? Carbon::parse($this->to) : $horizonTo;

        $pipeline->recalculate($this->productId, $this->storeId, $from, $to);
    }
}

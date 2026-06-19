<?php

namespace App\Jobs;

use App\Models\AvailabilitySnapshot;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Sample snapshot integrity check with auto-repair
 * (availability-engine.md §"Jobs" — `VerifySnapshotIntegrity`).
 *
 * Snapshots are derived from the authoritative `demands` table. A missed
 * recalculation (a dropped queue job, a crash mid-upsert) can leave a snapshot's
 * `available` out of step with what the demands now imply. This job samples a
 * handful of product/store pairs that have snapshots, captures their current
 * stored `available` per slot, re-runs the {@see RecalculationPipeline} (which
 * recomputes from live demands and upserts — the auto-repair), and compares: any
 * slot whose stored value differed from the recomputed one is logged as drift.
 *
 * Because the recompute upserts, drift is corrected by the act of detecting it.
 * The log is the warning channel; the data is left consistent.
 *
 * **Idempotent.** A clean run changes nothing (recompute equals stored); a run on
 * drifted data converges it. Re-running never diverges.
 *
 * **Replay-safe.** Reads `demands`, writes only snapshots/summaries/events via the
 * pipeline — never demands or the event store. Not dispatched during a replay.
 */
class VerifySnapshotIntegrity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  int  $sampleSize  number of product/store pairs to verify
     */
    public function __construct(
        public int $sampleSize = 5,
    ) {
        $this->onQueue((string) config('availability.recalc.queue', 'availability'));
    }

    public function handle(RecalculationPipeline $pipeline): void
    {
        $sample = $this->samplePairs();

        if ($sample === []) {
            return;
        }

        $now = Carbon::now('UTC');
        $pastDays = (int) settings('availability.snapshot_horizon_past_days', 90);
        $futureDays = (int) settings('availability.snapshot_horizon_future_days', 365);

        $from = $now->copy()->subDays(max(0, $pastDays))->startOfDay();
        $to = $now->copy()->addDays(max(0, $futureDays))->endOfDay();

        foreach ($sample as [$productId, $storeId]) {
            // Capture the stored availabilities keyed by slot before recomputing.
            $before = AvailabilitySnapshot::query()
                ->forProductStore($productId, $storeId)
                ->inWindow($from, $to)
                ->pluck('available', 'slot_start');

            $pipeline->recalculate($productId, $storeId, $from, $to);

            $after = AvailabilitySnapshot::query()
                ->forProductStore($productId, $storeId)
                ->inWindow($from, $to)
                ->pluck('available', 'slot_start');

            $drifted = 0;

            foreach ($after as $slot => $available) {
                if (! $before->has($slot) || (int) $before->get($slot) !== (int) $available) {
                    $drifted++;
                }
            }

            if ($drifted > 0) {
                Log::warning('Availability snapshot drift detected and repaired.', [
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'drifted_slots' => $drifted,
                ]);
            }
        }
    }

    /**
     * A random sample of distinct product/store pairs that currently have
     * snapshots.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function samplePairs(): array
    {
        return AvailabilitySnapshot::query()
            ->select('product_id', 'store_id')
            ->distinct()
            ->inRandomOrder()
            ->limit(max(1, $this->sampleSize))
            ->get()
            ->map(static fn (AvailabilitySnapshot $row): array => [(int) $row->product_id, (int) $row->store_id])
            ->all();
    }
}

<?php

namespace App\Jobs;

use App\Models\AvailabilityDailySummary;
use App\Models\AvailabilityEvent;
use App\Models\AvailabilitySnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Prune availability data beyond its retention window
 * (availability-engine.md §"Jobs" — `PruneAvailabilityData`).
 *
 * Three independent retention policies, each driven by a setting:
 *
 *  - **Snapshots** older than `availability.snapshot_horizon_past_days` (by
 *    `slot_start`): they fall outside the rolling horizon the pipeline
 *    materialises, so they are stale and never read by range queries (point
 *    queries read `demands` directly, not snapshots).
 *  - **Daily summaries** older than `availability.daily_summary_retention_years`
 *    (by `date`).
 *  - **Availability events** older than `availability.event_log_retention_months`
 *    (by `created_at`) — the append-only audit log.
 *
 * **Idempotent.** Deletes by an absolute cutoff computed at run time; a second
 * run finds nothing new past the (now later) cutoff that the first did not
 * already remove. Safe to re-run and to schedule daily.
 *
 * **Replay-safe.** Touches only derived/audit tables (snapshots, summaries,
 * events) — never `demands` or the Verbs event store — so it cannot perturb a
 * replay, and it is never dispatched during one.
 *
 * Deletes in bounded chunks so a large backlog does not hold one long
 * transaction or exhaust memory.
 */
class PruneAvailabilityData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue((string) config('availability.recalc.queue', 'availability'));
    }

    public function handle(): void
    {
        $now = Carbon::now('UTC');
        $chunk = max(1, (int) config('availability.prune.chunk_size', 1000));

        $snapshotCutoff = $now->copy()
            ->subDays(max(0, (int) settings('availability.snapshot_horizon_past_days', 90)))
            ->startOfDay();

        $this->pruneInChunks(
            AvailabilitySnapshot::query()->where('slot_start', '<', $snapshotCutoff),
            $chunk,
        );

        $summaryCutoff = $now->copy()
            ->subYears(max(1, (int) settings('availability.daily_summary_retention_years', 3)))
            ->startOfDay();

        $this->pruneInChunks(
            AvailabilityDailySummary::query()->where('date', '<', $summaryCutoff),
            $chunk,
        );

        $eventCutoff = $now->copy()
            ->subMonths(max(1, (int) settings('availability.event_log_retention_months', 12)));

        $this->pruneInChunks(
            AvailabilityEvent::query()->where('created_at', '<', $eventCutoff),
            $chunk,
        );
    }

    /**
     * Delete the matched rows in bounded passes until the query is exhausted.
     *
     * Deletes by a chunk of primary keys rather than `DELETE ... LIMIT`: SQLite
     * is typically compiled without `SQLITE_ENABLE_UPDATE_DELETE_LIMIT`, so a
     * limited delete would error there. Selecting ids first works on every
     * driver and keeps each delete bounded.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     */
    private function pruneInChunks(Builder $query, int $chunk): void
    {
        $key = $query->getModel()->getQualifiedKeyName();

        do {
            $ids = (clone $query)->limit($chunk)->pluck($key)->all();

            if ($ids === []) {
                break;
            }

            $query->getModel()->newQuery()->whereIn($query->getModel()->getKeyName(), $ids)->delete();
        } while (count($ids) === $chunk);
    }
}

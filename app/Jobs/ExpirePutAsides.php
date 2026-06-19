<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Release put-aside demands whose hold has expired
 * (availability-engine.md §"Jobs" — `ExpirePutAsides`).
 *
 * SCAFFOLD ONLY. The `put_aside` demand source is deferred — there is no
 * PutAsideDemandResolver and no code path that writes `source_type = 'put_aside'`
 * demands yet (the source is listed in the spec's metadata schemas but not built
 * in this phase). This job exists so the maintenance surface and the scheduler
 * entry are in place; it early-returns as a no-op until the put-aside source
 * lands.
 *
 * When the source is built, this job will: load active `put_aside` demands whose
 * `metadata.expires_at` is in the past, void them (release the hold), and
 * trigger an availability recompute for each affected product/store — mirroring
 * the overdue sweep. Until then it does nothing and is safe to schedule.
 *
 * **Idempotent / replay-safe** by construction (it is currently a no-op).
 */
class ExpirePutAsides implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue((string) config('availability.recalc.queue', 'availability'));
    }

    public function handle(): void
    {
        // No-op: the put_aside demand source is not implemented yet. See the
        // class docblock for the intended behaviour once it lands.
    }
}

<?php

use App\Jobs\ExpirePutAsides;
use App\Jobs\ExpireWaitlistMonitors;
use App\Jobs\PruneAvailabilityData;
use App\Jobs\VerifySnapshotIntegrity;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler Heartbeat
|--------------------------------------------------------------------------
*/

Schedule::call(fn () => Cache::put('scheduler:last-run', now(), 300))->everyMinute();
Schedule::command('action-log:prune')->dailyAt('02:00');
Schedule::command('model:prune')->daily();

// Extend overdue, unreturned demands to the sentinel so availability keeps
// reflecting un-returned stock. Idempotent and bounded per run; without
// overlapping so a long sweep never stacks, and on a single server in a cluster.
Schedule::command('availability:detect-overdue-demands')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Prune availability snapshots/summaries/events beyond their retention windows.
// Idempotent (deletes by absolute cutoff); withoutOverlapping so a large backlog
// never stacks, onOneServer in a cluster.
Schedule::job(new PruneAvailabilityData)
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

// Nightly sample integrity check — recomputes a handful of product/store pairs
// and repairs+logs any snapshot drift.
Schedule::job(new VerifySnapshotIntegrity)
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

// Release expired put-aside holds. No-op until the put_aside demand source is
// built (the job early-returns); scheduled now so the entry is in place.
Schedule::job(new ExpirePutAsides)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Expire stale shortage waitlist monitors (shortage-resolution-sub-hires.md
// §4.6). Idempotent; withoutOverlapping so a large backlog never stacks,
// onOneServer in a cluster.
Schedule::job(new ExpireWaitlistMonitors)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

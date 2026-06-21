<?php

use App\Jobs\ExpirePutAsides;
use App\Jobs\ExpireWaitlistMonitors;
use App\Jobs\PruneAvailabilityData;
use App\Jobs\VerifySnapshotIntegrity;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

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

/**
 * Build a cron expression for the overdue-demand sweep from the configured
 * interval (`availability.overdue_check_interval`, minutes; default 60 = hourly).
 *
 * SAFETY: schedule registration runs on EVERY console boot — including
 * `migrate`, `migrate:fresh`, and fresh installs where the `settings` table does
 * not exist yet. The settings read is therefore guarded by a table-existence
 * check (further wrapped in a try/catch in case the DB connection itself is
 * unavailable), falling back to 60 minutes so artisan never crashes
 * pre-migration. With the default (unset) value this yields `0 * * * *` —
 * identical to the previous `->hourly()` behaviour.
 *
 * Mapping minutes to a cron expression:
 *   - under 60 minutes: a "step minutes" expression (every N minutes within the
 *     hour);
 *   - a whole number of hours: a "step hours" expression (top of every Hth hour);
 *   - otherwise rounded up to the nearest whole hour so the expression stays
 *     valid and the sweep never runs more often than requested.
 */
$overdueCheckCron = (static function (): string {
    $minutes = 60;

    try {
        if (Schema::hasTable('settings')) {
            $minutes = (int) settings('availability.overdue_check_interval', 60);
        }
    } catch (Throwable) {
        $minutes = 60;
    }

    $minutes = max(1, min(1440, $minutes));

    if ($minutes < 60) {
        return sprintf('*/%d * * * *', $minutes);
    }

    $hours = max(1, (int) ceil($minutes / 60));

    return match (true) {
        $hours >= 24 => '0 0 * * *',
        $hours === 1 => '0 * * * *', // hourly — identical to the previous ->hourly()
        default => sprintf('0 */%d * * *', $hours),
    };
})();

// Extend overdue, unreturned demands to the sentinel so availability keeps
// reflecting un-returned stock. Idempotent and bounded per run; without
// overlapping so a long sweep never stacks, and on a single server in a cluster.
// Frequency derives from availability.overdue_check_interval (minutes); see the
// $overdueCheckCron builder above for the safe, pre-migration-tolerant mapping.
Schedule::command('availability:detect-overdue-demands')
    ->cron($overdueCheckCron)
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

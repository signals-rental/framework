<?php

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

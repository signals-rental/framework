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

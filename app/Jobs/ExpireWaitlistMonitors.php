<?php

namespace App\Jobs;

use App\Enums\WaitlistMonitorStatus;
use App\Models\ShortageWaitlistMonitor;
use App\Services\Shortages\ShortageEventRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Expire stale shortage waitlist monitors (shortage-resolution-sub-hires.md §4.6).
 *
 * Scheduled hourly. Loads ACTIVE {@see ShortageWaitlistMonitor} rows whose
 * `expires_at` has passed, flips each to Expired, and fires
 * `shortage.waitlist.expired`. Bounded and idempotent: a second run finds no
 * active-and-expired rows, so re-running is safe.
 *
 * Notification on expiry is part of the M6 notification engine; this job only
 * performs the state transition and emits the lifecycle event.
 */
class ExpireWaitlistMonitors implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue((string) config('availability.recalc.queue', 'availability'));
    }

    public function handle(ShortageEventRecorder $events): void
    {
        $now = Carbon::now();

        ShortageWaitlistMonitor::query()
            ->active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get()
            ->each(function (ShortageWaitlistMonitor $monitor) use ($events): void {
                $monitor->update([
                    'status' => WaitlistMonitorStatus::Expired->value,
                ]);

                $events->waitlistExpired($monitor->fresh() ?? $monitor);
            });
    }
}

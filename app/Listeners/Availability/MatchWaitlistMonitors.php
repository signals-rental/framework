<?php

namespace App\Listeners\Availability;

use App\Enums\WaitlistMonitorStatus;
use App\Events\Availability\AvailabilityChanged;
use App\Models\ShortageWaitlistMonitor;
use App\Services\AvailabilityService;
use App\Services\Shortages\ShortageEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Facades\Verbs;

/**
 * Waitlist matching (shortage-resolution-sub-hires.md §4.6).
 *
 * Listens for {@see AvailabilityChanged} and, for the changed product/store,
 * checks every ACTIVE {@see ShortageWaitlistMonitor}: when the availability engine
 * now reports enough free stock over the monitor's window to satisfy its
 * `quantity_needed`, the monitor flips to Matched and `shortage.waitlist.matched`
 * fires.
 *
 * Notification on match is M6 — left as a clearly-marked stub here (the event is
 * emitted; the user alert is a later wiring step).
 *
 * Replay-safe: {@see AvailabilityChanged} is never dispatched during replay, and
 * this listener short-circuits on replay as a guard.
 */
class MatchWaitlistMonitors implements ShouldQueue
{
    public function viaQueue(): string
    {
        return (string) config('availability.recalc.queue', 'availability');
    }

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ShortageEventRecorder $events,
    ) {}

    public function handle(AvailabilityChanged $event): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        ShortageWaitlistMonitor::query()
            ->active()
            ->where('product_id', $event->productId)
            ->where('store_id', $event->storeId)
            ->get()
            ->each(function (ShortageWaitlistMonitor $monitor): void {
                $from = $monitor->starts_at ?? Carbon::now();
                $to = $monitor->ends_at ?? $from->copy()->addDay();

                $satisfied = $this->availability->checkAvailability(
                    $monitor->product_id,
                    $monitor->store_id,
                    Carbon::parse($from),
                    Carbon::parse($to),
                    $monitor->quantity_needed,
                );

                if (! $satisfied) {
                    return;
                }

                $monitor->update([
                    'status' => WaitlistMonitorStatus::Matched->value,
                    'matched_at' => Carbon::now(),
                ]);

                $this->events->waitlistMatched($monitor->fresh() ?? $monitor, [
                    'quantity_needed' => $monitor->quantity_needed,
                ]);

                // M6: notify — alert the waitlisting user that stock is now free.
                // The match event is emitted above; the user-facing notification is
                // wired in M6 (notification engine), not here.
            });
    }
}

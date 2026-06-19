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

                // Recorded stub (assessed in M6, deferred): a user-facing
                // "stock is now free" notification on match needs consumer infra
                // that is not yet built — the monitor carries no recipient
                // (no user/member column on shortage_waitlist_monitors, and the
                // backing ShortageResolution holds no notifiable owner), so there
                // is no notifiable to resolve, and no `shortage.waitlist.matched`
                // NotificationType / Notification class exists to apply per-user
                // channel preferences against. The `shortage.waitlist.matched`
                // audit/event is emitted above (the durable signal); the user
                // alert lands when the monitor gains a recipient and the
                // notification engine grows a waitlist consumer. The pull-based
                // surfacing — `matched_at` flips and the resolution shows Matched —
                // is fully wired today.
            });
    }
}

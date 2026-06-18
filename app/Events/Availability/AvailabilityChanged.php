<?php

namespace App\Events\Availability;

use App\Jobs\RecalculateAvailabilityJob;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast over Reverb when a product/store's availability read model has been
 * refreshed by a {@see RecalculationPipeline} run.
 *
 * Emitted from {@see RecalculateAvailabilityJob} after the recompute
 * commits, on a per-store private channel (`availability.store.{storeId}`), so a
 * calendar/grid UI scoped to a store receives a live nudge to re-fetch the
 * affected product's slots. The payload is intentionally a light summary — the
 * client re-reads the snapshot/range endpoints for authoritative numbers rather
 * than trusting a pushed value.
 *
 * Replay-safety: this event is never fired during a `Verbs::replay()`. It is
 * dispatched only from the recalc job, and the observers that enqueue that job do
 * not dispatch while replaying (demand sync is `Verbs::unlessReplaying()`-guarded
 * and the observers additionally short-circuit on replay), so no broadcast is
 * produced when rebuilding the event store.
 */
class AvailabilityChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int  $productId  The product whose availability changed.
     * @param  int  $storeId  The store the change is scoped to.
     * @param  string|null  $from  ISO-8601 start of the refreshed window, when known.
     * @param  string|null  $to  ISO-8601 end of the refreshed window, when known.
     * @param  int|null  $slots  Number of snapshot slots refreshed, when known.
     */
    public function __construct(
        public int $productId,
        public int $storeId,
        public ?string $from = null,
        public ?string $to = null,
        public ?int $slots = null,
    ) {}

    /**
     * The private channel this event broadcasts on, scoped per store so a
     * store-bound consumer only receives changes relevant to it.
     *
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('availability.store.'.$this->storeId),
        ];
    }

    /**
     * The wire event name (kept stable and snake_case for JS consumers).
     */
    public function broadcastAs(): string
    {
        return 'availability.changed';
    }

    /**
     * The broadcast payload — a light summary; clients re-read for exact numbers.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'from' => $this->from,
            'to' => $this->to,
            'slots' => $this->slots,
        ];
    }
}

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
 * Emitted from {@see RecalculateAvailabilityJob} after the recompute commits, on
 * the availability channels from availability-engine.md §"Real-Time Updates":
 *
 *  - `availability.product.{productId}.store.{storeId}` — the most specific
 *    channel a Gantt/calendar bound to one product at one store subscribes to.
 *  - `availability.store.{storeId}` — any product changing at the store.
 *  - `availability.shortages` — global shortage alerts; only carries a meaningful
 *    signal when {@see $hasShortage} is true, but every recalc broadcasts here so
 *    a dashboard can clear a previously-flagged shortage too.
 *
 * The plan also lists `availability.opportunity.{opportunityId}`, but this event
 * carries only product/store/window context — it is fired from the product/store
 * recalc job and has no opportunity association — so that channel is deliberately
 * OMITTED here. An opportunity-scoped availability broadcast belongs on an
 * opportunity-aware event (a future shortage/opportunity recalc), not on this
 * product/store recompute notification.
 *
 * The payload is a light summary — the client re-reads the snapshot/range
 * endpoints for authoritative numbers rather than trusting a pushed value.
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
     * @param  bool  $hasShortage  Whether any refreshed slot dipped below zero.
     */
    public function __construct(
        public int $productId,
        public int $storeId,
        public ?string $from = null,
        public ?string $to = null,
        public ?int $slots = null,
        public bool $hasShortage = false,
    ) {}

    /**
     * The private channels this event broadcasts on: the specific product/store
     * channel, the store-wide channel, and the global shortages channel.
     *
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('availability.product.'.$this->productId.'.store.'.$this->storeId),
            new PrivateChannel('availability.store.'.$this->storeId),
            new PrivateChannel('availability.shortages'),
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
            'has_shortage' => $this->hasShortage,
        ];
    }
}

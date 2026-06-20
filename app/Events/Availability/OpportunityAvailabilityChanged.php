<?php

namespace App\Events\Availability;

use App\Jobs\RecalculateAvailabilityJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast over Reverb when a product/store availability recompute affects a
 * specific opportunity — the opportunity-scoped companion to
 * {@see AvailabilityChanged}.
 *
 * {@see AvailabilityChanged} carries only product/store context and so cannot
 * reach the `availability.opportunity.{id}` channel from
 * availability-engine.md §"Real-Time Updates". This event is dispatched, in
 * addition to that product/store broadcast, once per opportunity that has an
 * active demand for the recalculated product/store within the refreshed window
 * (resolved by {@see RecalculateAvailabilityJob}). The M8 opportunity Show page
 * subscribes to its own channel for live per-line availability badges without
 * having to listen to every product/store channel.
 *
 * Payload mirrors {@see AvailabilityChanged} (product/store/window/shortage
 * summary) plus the `opportunity_id`; clients re-read the opportunity
 * availability endpoint for authoritative per-line numbers.
 *
 * Replay-safety: dispatched only from {@see RecalculateAvailabilityJob}, which
 * the (replay-skipped) demand/stock observers enqueue, so no broadcast is
 * produced while rebuilding the event store.
 */
class OpportunityAvailabilityChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int  $opportunityId  The opportunity whose availability picture changed.
     * @param  int  $productId  The product whose availability changed.
     * @param  int  $storeId  The store the change is scoped to.
     * @param  string|null  $from  ISO-8601 start of the refreshed window, when known.
     * @param  string|null  $to  ISO-8601 end of the refreshed window, when known.
     * @param  bool  $hasShortage  Whether any refreshed slot dipped below zero.
     */
    public function __construct(
        public int $opportunityId,
        public int $productId,
        public int $storeId,
        public ?string $from = null,
        public ?string $to = null,
        public bool $hasShortage = false,
    ) {}

    /**
     * The opportunity-scoped private channel this event broadcasts on.
     *
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('availability.opportunity.'.$this->opportunityId),
        ];
    }

    /**
     * The wire event name — kept identical to {@see AvailabilityChanged} so a
     * client listens for one event name across both channel families.
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
            'opportunity_id' => $this->opportunityId,
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'from' => $this->from,
            'to' => $this->to,
            'has_shortage' => $this->hasShortage,
        ];
    }
}

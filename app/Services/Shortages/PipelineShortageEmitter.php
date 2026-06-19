<?php

namespace App\Services\Shortages;

use App\Enums\AvailabilityEventType;
use App\Events\Availability\AvailabilityChanged;
use App\Listeners\Availability\DetectOrderShortages;
use App\Models\AvailabilityEvent;
use Illuminate\Support\Carbon;

/**
 * Emits PRODUCT/STORE-scoped shortage events from the recalculation pipeline
 * (shortage-resolution-sub-hires.md §2.4 "On availability change events", and the
 * Domain-B drift item: stock-write-induced shortages must emit events too).
 *
 * This is the AUTHORITATIVE emission point for product/store availability
 * crossings: when a recalc pushes any slot below zero (or recovers it), one
 * `shortage_detected` / `shortage_resolved` row is written against
 * `source_type = 'product_store'`.
 *
 * Coordination with the reactive listener (§2.4 "confirmed orders"): the
 * {@see DetectOrderShortages} listener on
 * {@see AvailabilityChanged} emits at the
 * OPPORTUNITY-ITEM granularity (`source_type = 'opportunity_item'`) — naming the
 * specific confirmed order that is now short. Because the two emit at different
 * source scopes, a single recalc never produces a duplicate row for the same
 * shortage: the pipeline answers "this product/store dipped negative", the
 * listener answers "this order is affected".
 *
 * Never writes Verbs state — append-only `availability_events` only — and the
 * caller already guards against replay.
 */
class PipelineShortageEmitter
{
    /**
     * Record a product/store shortage crossing for a recalculated window.
     */
    public function emitForRecalc(
        int $productId,
        int $storeId,
        Carbon $from,
        Carbon $to,
        bool $crossedIntoShortage,
        bool $crossedOutOfShortage,
    ): void {
        if ($crossedIntoShortage) {
            $this->log(AvailabilityEventType::ShortageDetected, $productId, $storeId, $from, $to, 'recalculation');
        }

        if ($crossedOutOfShortage) {
            $this->log(AvailabilityEventType::ShortageResolved, $productId, $storeId, $from, $to, 'recalculation');
        }
    }

    private function log(
        AvailabilityEventType $type,
        int $productId,
        int $storeId,
        Carbon $from,
        Carbon $to,
        string $reason,
    ): void {
        AvailabilityEvent::query()->create([
            'event_type' => $type,
            'product_id' => $productId,
            'store_id' => $storeId,
            'source_type' => 'product_store',
            'source_id' => $productId,
            'payload' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'reason' => $reason,
            ],
        ]);
    }
}

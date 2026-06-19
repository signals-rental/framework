<?php

namespace App\Data\Availability;

use App\Data\Concerns\FormatsTimestamps;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * The availability picture for a single opportunity line item.
 *
 * Surfaced by {@see App\Services\AvailabilityService::getOpportunityContext()} so
 * the quote/order screen can show, per line, how many units are free over the
 * line's own window at the line's own store (its `dispatch_store_id` override or
 * the opportunity's store) — and whether the line is short.
 *
 * `available_for_item` is the worst (most-constrained) slot over the line's
 * window with the line's OWN demand excluded — i.e. how many units the line
 * could fulfil. `shortage_quantity` is the positive shortfall against the line's
 * requested quantity (zero when the line is fully satisfiable).
 */
class OpportunityItemAvailabilityData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $opportunity_item_id,
        public ?int $product_id,
        public int $store_id,
        public int $requested_quantity,
        public int $available_for_item,
        public int $shortage_quantity,
        public bool $has_shortage,
        public string $from,
        public string $to,
    ) {}

    public static function make(
        int $opportunityItemId,
        ?int $productId,
        int $storeId,
        int $requestedQuantity,
        int $availableForItem,
        Carbon $from,
        Carbon $to,
    ): self {
        $shortage = max(0, $requestedQuantity - $availableForItem);

        return new self(
            opportunity_item_id: $opportunityItemId,
            product_id: $productId,
            store_id: $storeId,
            requested_quantity: $requestedQuantity,
            available_for_item: $availableForItem,
            shortage_quantity: $shortage,
            has_shortage: $shortage > 0,
            from: self::formatTimestamp($from),
            to: self::formatTimestamp($to),
        );
    }
}

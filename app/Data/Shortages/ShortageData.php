<?php

namespace App\Data\Shortages;

use App\Data\Concerns\FormatsTimestamps;
use App\Enums\StockMethod;
use App\ValueObjects\Shortage;
use Spatie\LaravelData\Data;

/**
 * API representation of a computed {@see Shortage} value object
 * (shortage-resolution-sub-hires.md §2.3). Shortages are never persisted, so this
 * DTO is built from the value object, not a model.
 */
class ShortageData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $opportunity_item_id,
        public int $opportunity_id,
        public int $product_id,
        public string $product_name,
        public int $store_id,
        public int $requested_quantity,
        public int $available_quantity,
        public int $shortfall,
        public int $remaining_shortfall,
        public string $tracking_type,
        public string $starts_at,
        public string $ends_at,
        public bool $is_critical,
    ) {}

    public static function fromShortage(Shortage $shortage): self
    {
        return new self(
            opportunity_item_id: $shortage->opportunityItemId,
            opportunity_id: $shortage->opportunityId,
            product_id: $shortage->productId,
            product_name: $shortage->productName,
            store_id: $shortage->storeId,
            requested_quantity: $shortage->requestedQuantity,
            available_quantity: $shortage->availableQuantity,
            shortfall: $shortage->shortfall,
            remaining_shortfall: $shortage->remainingShortfall(),
            tracking_type: $shortage->trackingType === StockMethod::Serialised ? 'serialised' : 'bulk',
            starts_at: self::formatTimestamp($shortage->startsAt),
            ends_at: self::formatTimestamp($shortage->endsAt),
            is_critical: $shortage->isCritical,
        );
    }
}

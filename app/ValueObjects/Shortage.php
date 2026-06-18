<?php

namespace App\ValueObjects;

use App\Enums\StockMethod;
use App\Models\Demand;
use App\Services\Shortages\ShortageDetector;
use Illuminate\Support\Carbon;

/**
 * A computed inventory shortage for a single opportunity line item over its
 * effective period (shortage-resolution-sub-hires.md §1.3, §2.3).
 *
 * Shortages are NOT persisted: a shortage is the gap between what a line item
 * needs and what the availability engine reports as free at query time, and it
 * changes as other bookings, returns, and stock movements occur. Only resolution
 * records are stored. This value object is the typed result of
 * {@see ShortageDetector} and the payload of the
 * `shortage.detected` event.
 *
 * `remaining_shortfall` is the resolution-facing figure: the raw shortfall minus
 * the quantity already covered by active resolution records for the item.
 * Resolvers receive the remaining shortfall, never the gross.
 */
final readonly class Shortage
{
    public function __construct(
        public int $opportunityItemId,
        public int $opportunityId,
        public int $productId,
        public string $productName,
        public int $storeId,
        public int $requestedQuantity,
        public int $availableQuantity,
        public int $shortfall,
        public StockMethod $trackingType,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public bool $isCritical,
        public int $resolvedQuantity = 0,
    ) {}

    /**
     * Build a shortage from its raw quantities, deriving shortfall (clamped at
     * zero — callers should only construct a Shortage when a positive shortfall
     * exists, but the clamp keeps the invariant `shortfall >= 0` regardless).
     */
    public static function make(
        int $opportunityItemId,
        int $opportunityId,
        int $productId,
        string $productName,
        int $storeId,
        int $requestedQuantity,
        int $availableQuantity,
        StockMethod $trackingType,
        Carbon $startsAt,
        Carbon $endsAt,
        bool $isCritical,
        int $resolvedQuantity = 0,
    ): self {
        return new self(
            opportunityItemId: $opportunityItemId,
            opportunityId: $opportunityId,
            productId: $productId,
            productName: $productName,
            storeId: $storeId,
            requestedQuantity: $requestedQuantity,
            availableQuantity: $availableQuantity,
            shortfall: max(0, $requestedQuantity - $availableQuantity),
            trackingType: $trackingType,
            startsAt: $startsAt,
            endsAt: $endsAt,
            isCritical: $isCritical,
            resolvedQuantity: max(0, $resolvedQuantity),
        );
    }

    /**
     * Shortfall still unresolved after active resolution records — the figure
     * resolvers act on. Never negative.
     */
    public function remainingShortfall(): int
    {
        return max(0, $this->shortfall - $this->resolvedQuantity);
    }

    /**
     * Whether any unresolved shortfall remains. A shortage whose remaining
     * shortfall has reached zero is considered cleared for gate purposes.
     */
    public function isUnresolved(): bool
    {
        return $this->remainingShortfall() > 0;
    }

    /**
     * The inline line-item badge payload (shortage-resolution-sub-hires.md §10.4
     * `opportunity.item.badges`). A compact shape the UI renders on each line.
     *
     * @return array{
     *     opportunity_item_id: int,
     *     product_id: int,
     *     product_name: string,
     *     requested_quantity: int,
     *     available_quantity: int,
     *     shortfall: int,
     *     remaining_shortfall: int,
     *     tracking_type: string,
     *     is_critical: bool
     * }
     */
    public function toBadge(): array
    {
        return [
            'opportunity_item_id' => $this->opportunityItemId,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'requested_quantity' => $this->requestedQuantity,
            'available_quantity' => $this->availableQuantity,
            'shortfall' => $this->shortfall,
            'remaining_shortfall' => $this->remainingShortfall(),
            'tracking_type' => $this->trackingType === StockMethod::Serialised ? 'serialised' : 'bulk',
            'is_critical' => $this->isCritical,
        ];
    }

    /**
     * A snapshot of this shortage for the acknowledgement audit trail
     * (shortage-resolution-sub-hires.md §7.3 `shortages_snapshot`). ISO-8601 UTC
     * dates so the snapshot is portable and replay-stable.
     *
     * An open-ended window (the demand sentinel "no known end" date) is emitted as
     * `ends_at: null` with `open_ended: true`, rather than leaking the internal
     * {@see Demand::SENTINEL_DATE} far-future sentinel into the audit record.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        $openEnded = $this->endsAt->equalTo(Carbon::parse(Demand::SENTINEL_DATE));

        return $this->toBadge() + [
            'starts_at' => $this->startsAt->utc()->toIso8601String(),
            'ends_at' => $openEnded ? null : $this->endsAt->utc()->toIso8601String(),
            'open_ended' => $openEnded,
            'store_id' => $this->storeId,
        ];
    }
}

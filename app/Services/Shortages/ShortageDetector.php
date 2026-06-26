<?php

namespace App\Services\Shortages;

use App\Enums\OpportunityState;
use App\Enums\ShortageResolutionStatus;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ShortageResolutionItem;
use App\Services\AvailabilityService;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Detects inventory shortages on opportunities by comparing line-item demand
 * against what the availability engine reports as free
 * (shortage-resolution-sub-hires.md §2).
 *
 * The detector is a thin, opportunity-aware consumer of the
 * {@see AvailabilityService} — it never modifies the engine. For each
 * product-backed line item it asks the engine how many units are free for that
 * item over its effective window (excluding the item's own demand, via
 * {@see AvailabilityService::availableForItem()}), derives the shortfall, then
 * nets off any active resolution records to produce `remaining_shortfall`.
 *
 * Shortages are computed, never stored: the {@see ShortageCollection} this
 * returns is the typed result feeding the confirmation gate and the per-
 * opportunity badge/API endpoint.
 */
class ShortageDetector
{
    public function __construct(
        private readonly AvailabilityService $availability,
    ) {}

    /**
     * Detect shortages across every product-backed line item of an opportunity
     * (shortage-resolution-sub-hires.md §2.2, single-opportunity scope). Only
     * items with a positive shortfall are returned.
     */
    public function forOpportunity(Opportunity $opportunity): ShortageCollection
    {
        // Eager-load the polymorphic product on every line so the per-item
        // resolveProduct() reads the loaded relation instead of issuing one
        // Product::find() query per product-backed line (N+1).
        $opportunity->loadMissing('items.item');

        $shortages = new ShortageCollection;

        foreach ($opportunity->items as $item) {
            $shortage = $this->forItem($item, $opportunity);

            if ($shortage !== null) {
                $shortages->push($shortage);
            }
        }

        return $shortages;
    }

    /**
     * Detect a shortage for a single line item (shortage-resolution-sub-hires.md
     * §2.2, single-item scope), used inline when adding/editing a line. Returns
     * null when the item is fully serviceable or is not product-backed.
     */
    public function forItem(OpportunityItem $item, ?Opportunity $opportunity = null): ?Shortage
    {
        $opportunity ??= $item->opportunity;

        $product = $this->resolveProduct($item);
        $storeId = $opportunity->store_id;

        if ($product === null || $storeId === null || ! $product->track_availability) {
            return null;
        }

        $requested = $this->requestedQuantity($item);

        if ($requested <= 0) {
            return null;
        }

        [$startsAt, $endsAt] = $this->resolveDates($item, $opportunity);

        $available = $this->availability->availableForItem(
            $product->id,
            $storeId,
            $startsAt,
            $endsAt,
            'opportunity_item',
            $item->id,
        );

        $shortfall = $requested - $available;

        if ($shortfall <= 0) {
            return null;
        }

        return Shortage::make(
            opportunityItemId: $item->id,
            opportunityId: $opportunity->id,
            productId: $product->id,
            productName: $product->name,
            storeId: $storeId,
            requestedQuantity: $requested,
            availableQuantity: max(0, $available),
            trackingType: $product->stock_method ?? StockMethod::Bulk,
            startsAt: $startsAt,
            endsAt: $endsAt,
            isCritical: $opportunity->state === OpportunityState::Order,
            resolvedQuantity: $this->resolvedQuantityFor($item->id),
        );
    }

    /**
     * The requested quantity for a line item: the count of allocated serialised
     * assets when present (each is one demand of one unit), otherwise the line
     * quantity rounded to whole units. Mirrors the demand resolver's quantity
     * resolution so detection and demand agree.
     */
    private function requestedQuantity(OpportunityItem $item): int
    {
        $allocated = $item->assets()->whereNotNull('stock_level_id')->count();

        if ($allocated > 0) {
            return $allocated;
        }

        return max(0, (int) round((float) $item->quantity));
    }

    /**
     * Active resolution coverage already recorded for a line item — the quantity
     * netted off to produce `remaining_shortfall` (§2.3). Summed in a single
     * aggregate query over the resolution-item pivot, scoped to active resolutions.
     */
    private function resolvedQuantityFor(int $opportunityItemId): int
    {
        return (int) ShortageResolutionItem::query()
            ->whereHas('resolution', static fn (Builder $query): Builder => $query->whereNotIn('status', [
                ShortageResolutionStatus::Cancelled->value,
                ShortageResolutionStatus::Failed->value,
            ]))
            ->where('opportunity_item_id', $opportunityItemId)
            ->sum('quantity_allocated');
    }

    /**
     * Resolve the effective demand window for a line item, inheriting the
     * opportunity's dates when the item's own are null and treating a missing end
     * as indefinite (the sentinel). Mirrors the demand resolver so the period the
     * detector checks matches the period the demand claims.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDates(OpportunityItem $item, Opportunity $opportunity): array
    {
        $startsAt = $item->starts_at ?? $opportunity->starts_at ?? Carbon::now();
        $endsAt = $item->ends_at ?? $opportunity->ends_at ?? Demand::sentinel();

        return [Carbon::parse($startsAt), Carbon::parse($endsAt)];
    }

    /**
     * Resolve the catalogue product a line item refers to via its polymorphic
     * `itemable_type`/`itemable_id`. Non-product lines (services, ad-hoc) resolve to
     * null and never produce a shortage.
     *
     * NOTE: resolved with an explicit find() (rather than the polymorphic `itemable`
     * relation) because `itemable_type` may be stored as the short `product` morph
     * alias, which the morphTo relation cannot resolve without a registered morph
     * map — find() honours {@see OpportunityItem::isProductBacked()} for both forms.
     */
    private function resolveProduct(OpportunityItem $item): ?Product
    {
        if (! $item->isProductBacked()) {
            return null;
        }

        // Prefer the eager-loaded polymorphic relation (populated by forOpportunity)
        // to avoid a query per item; fall back to a direct lookup on the single-item
        // path where the relation was not pre-loaded.
        if ($item->relationLoaded('item') && $item->item instanceof Product) {
            return $item->item;
        }

        return Product::query()->find($item->itemable_id);
    }
}

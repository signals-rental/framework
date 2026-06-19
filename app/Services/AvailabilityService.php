<?php

namespace App\Services;

use App\Data\Availability\AvailabilityData;
use App\Data\Availability\AvailabilityRangeData;
use App\Data\Availability\AvailabilitySlotData;
use App\Data\Availability\OpportunityItemAvailabilityData;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\KitAvailabilityCalculator;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\RecalculationPipeline;
use App\Services\Availability\SlotCalculator;
use App\Services\Shortages\ShortageDetector;
use BadMethodCallException;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * The read interface for the availability engine. Implements the two-tier read
 * strategy from the design:
 *
 *  1. **Point queries** ({@see getAvailability()}, {@see checkAvailability()})
 *     compute on-the-fly from the `demands` table — zero snapshot dependency.
 *  2. **Range/grid queries** ({@see getAvailabilityRange()}) read from
 *     pre-calculated `availability_snapshots`, surfacing `calculated_at` so
 *     consumers know how fresh the data is.
 *
 * Resolve via the container so the slot calculator's resolution provider can be
 * rebound (e.g. by the Cloud package).
 */
class AvailabilityService
{
    public function __construct(
        private readonly SlotCalculator $slotCalculator,
        private readonly RecalculationPipeline $pipeline,
        private readonly OpportunityItemDemandResolver $opportunityItemResolver,
    ) {}

    /**
     * Point availability for a product/store on a given date — computed live
     * from demands. The date is aligned to its resolution slot; demand
     * overlapping that slot reduces availability.
     */
    public function getAvailability(int $productId, int $storeId, Carbon $date): AvailabilityData
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            return AvailabilityData::make($productId, $storeId, $date, 0, 0, []);
        }

        $timezone = $this->storeTimezone($storeId);
        $slotStart = $this->slotCalculator->alignToSlot($date, $timezone);
        $slotEnd = $this->slotCalculator->advance($slotStart, $timezone);

        // Kit products are composed read-time from components — they have no stock
        // or demand of their own. Compose the slot's availability via the kit
        // calculator and surface it as a point reading.
        if ($product->is_kit) {
            $range = $this->getKitAvailability($productId, $storeId, $slotStart, $slotEnd);
            $available = $range->min_available ?? 0;

            return AvailabilityData::make($productId, $storeId, $slotStart, $available, 0, []);
        }

        $totalStock = $this->pipeline->totalStock($product, $storeId);

        [$demanded, $breakdown] = $this->sumDemand($productId, $storeId, $slotStart, $slotEnd);

        return AvailabilityData::make($productId, $storeId, $slotStart, $totalStock, $demanded, $breakdown);
    }

    /**
     * Range availability for a product/store, read from snapshots. Slots are
     * returned in chronological order; `calculated_at` reflects the oldest
     * snapshot in the range (null when none exist yet).
     */
    public function getAvailabilityRange(int $productId, int $storeId, Carbon $from, Carbon $to): AvailabilityRangeData
    {
        // Kit products hold no snapshot rows — their availability is composed
        // read-time from components. Route them to the kit calculator so a kit
        // passed to the normal range read "just works" rather than returning an
        // empty (and misleading) slot set.
        if ($this->isKitProduct($productId)) {
            return $this->getKitAvailability($productId, $storeId, $from, $to);
        }

        /** @var Collection<int, AvailabilitySnapshot> $snapshots */
        $snapshots = AvailabilitySnapshot::query()
            ->forProductStore($productId, $storeId)
            ->inWindow($from, $to)
            ->orderBy('slot_start')
            ->get();

        $slots = $snapshots
            ->map(static fn (AvailabilitySnapshot $snapshot): AvailabilitySlotData => AvailabilitySlotData::fromModel($snapshot))
            ->values()
            ->all();

        $calculatedAt = $snapshots
            ->map(static fn (AvailabilitySnapshot $snapshot): CarbonInterface => $snapshot->calculated_at)
            ->min();

        return AvailabilityRangeData::make($productId, $storeId, $from, $to, $slots, $calculatedAt);
    }

    /**
     * Range availability for a single product across several stores, read from
     * snapshots and keyed by store id.
     *
     * Each store's window is materialised via {@see getAvailabilityRange()} (the
     * snapshot read path). When `$storeIds` is empty the default-query stores are
     * used ({@see Store::scopeInDefaultQueries()}) so virtual/secondary stores
     * flagged out of default queries are excluded unless requested explicitly.
     * The result preserves the resolved store order.
     *
     * @param  list<int>  $storeIds  explicit stores; empty = all default-query stores
     * @return SupportCollection<int, AvailabilityRangeData> keyed by store id
     */
    public function getAvailabilityAcrossStores(
        int $productId,
        array $storeIds,
        Carbon $from,
        Carbon $to,
    ): SupportCollection {
        $resolvedStoreIds = $storeIds !== []
            ? array_values(array_unique(array_map('intval', $storeIds)))
            : Store::query()
                ->inDefaultQueries()
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

        /** @var SupportCollection<int, AvailabilityRangeData> $byStore */
        $byStore = new SupportCollection;

        foreach ($resolvedStoreIds as $storeId) {
            $byStore->put($storeId, $this->getAvailabilityRange($productId, $storeId, $from, $to));
        }

        return $byStore;
    }

    /**
     * The availability picture for every product-backed line item on an
     * opportunity — the per-line read surface for the quote/order screen.
     *
     * For each line that references a product, the line's resolved
     * product/store/window/quantity (date-source-aware, honouring a line's
     * `dispatch_store_id` override) is taken from the
     * {@see OpportunityItemDemandResolver}, and the units free for that line are
     * computed live via {@see availableForItem()} — the worst slot over the line's
     * window with the line's OWN demand excluded. Lines that reference no product
     * (services, ad-hoc lines) are skipped: they place no demand on stock.
     *
     * Returns an empty collection for an unknown opportunity. Computed live from
     * `demands` (no snapshot dependency), so it always reflects the current state.
     *
     * @return SupportCollection<int, OpportunityItemAvailabilityData>
     */
    public function getOpportunityContext(int $opportunityId): SupportCollection
    {
        $opportunity = Opportunity::query()->with('items')->find($opportunityId);

        if ($opportunity === null) {
            return new SupportCollection;
        }

        /** @var SupportCollection<int, OpportunityItemAvailabilityData> $context */
        $context = new SupportCollection;

        $opportunity->items->each(function (OpportunityItem $item) use ($opportunity, $context): void {
            // Bind the parent so the resolver reads the live opportunity (store,
            // dates, status) without an extra query per line.
            $item->setRelation('opportunity', $opportunity);

            $resolved = $this->opportunityItemResolver->resolveContext($item);

            // No product or no store — the line claims nothing against stock.
            if ($resolved['product_id'] === null || $resolved['store_id'] === null) {
                return;
            }

            $available = $this->availableForItem(
                $resolved['product_id'],
                $resolved['store_id'],
                $resolved['from'],
                $resolved['to'],
                $this->opportunityItemResolver->sourceType(),
                (int) $item->id,
            );

            $context->push(OpportunityItemAvailabilityData::make(
                opportunityItemId: (int) $item->id,
                productId: $resolved['product_id'],
                storeId: $resolved['store_id'],
                requestedQuantity: $resolved['quantity'],
                availableForItem: $available,
                from: $resolved['from'],
                to: $resolved['to'],
            ));
        });

        return $context;
    }

    /**
     * Whether at least `$quantity` units are available for the entire
     * `[$from, $to)` window. Computed live from demands across every slot the
     * window touches — true only if the worst slot still has the quantity.
     */
    public function checkAvailability(int $productId, int $storeId, Carbon $from, Carbon $to, int $quantity): bool
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            return false;
        }

        // Clamp an open-ended / sentinel-dated window to the rolling snapshot
        // horizon before generating slots — an indefinite demand otherwise spans
        // tens of thousands of slots and trips the SlotCalculator safety cap. The
        // demand set is fetched (and overlap-tested) against the ORIGINAL window so
        // an indefinite demand is still seen across the whole clamped horizon.
        [$slotFrom, $slotTo] = $this->clampToHorizon($from, $to);

        $timezone = $this->storeTimezone($storeId);
        $totalStock = $this->pipeline->totalStock($product, $storeId);

        /** @var Collection<int, Demand> $demands */
        $demands = Demand::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->overlapping($from, $to)
            ->get();

        foreach ($this->slotCalculator->generateSlots($slotFrom, $slotTo, $timezone) as $slotStart) {
            $slotEnd = $this->slotCalculator->advance($slotStart, $timezone);
            [$demanded] = $this->sumDemandIn($demands, $slotStart, $slotEnd);

            if ($totalStock - $demanded < $quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * The serialised stock-level "assets" of a product at a store that are free
     * for the entire `[$from, $to)` window — i.e. no active demand claims that
     * specific asset over a window overlapping the request.
     *
     * Each serialised stock level is one physical unit; an asset is free iff no
     * active `demands` row carries its `asset_id` with an overlapping period. The
     * overlap reuses {@see Demand::scopeOverlapping()} (the native `tstzrange &&`
     * path on PostgreSQL — backed by `idx_demands_asset_period` and the
     * `excl_demands_asset_period` exclusion constraint — and the degraded scalar
     * path on SQLite).
     *
     * Bulk products have no discrete assets, so an empty collection is returned
     * for them — callers wanting bulk availability use the quantity-based reads
     * ({@see getAvailability()} / {@see checkAvailability()}).
     *
     * @return Collection<int, StockLevel>
     */
    public function getAvailableAssets(int $productId, int $storeId, Carbon $from, Carbon $to): Collection
    {
        /** @var Collection<int, StockLevel> $assets */
        $assets = $this->availableAssetsQuery($productId, $storeId, $from, $to)->get();

        return $assets;
    }

    /**
     * The paginated free serialised assets of a product at a store for the
     * `[$from, $to)` window — the {@see getAvailableAssets()} query paginated at
     * the database level (real `LengthAwarePaginator`, not a sliced collection),
     * so the API exposes genuine total/per_page/page metadata.
     *
     * @return LengthAwarePaginator<int, StockLevel>
     */
    public function paginateAvailableAssets(
        int $productId,
        int $storeId,
        Carbon $from,
        Carbon $to,
        int $perPage = 50,
        int $page = 1,
    ): LengthAwarePaginator {
        return $this->availableAssetsQuery($productId, $storeId, $from, $to)
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * The shared query for free serialised assets over `[$from, $to)`.
     *
     * A serialised stock level is free iff no active demand claims its `asset_id`
     * over an overlapping period. The conflict subquery reuses the driver-aware
     * {@see Demand::scopeOverlapping()} (native `tstzrange &&` on Postgres, scalar
     * on SQLite). Bulk products have no discrete assets, so the query naturally
     * yields nothing for them.
     *
     * @return EloquentBuilder<StockLevel>
     */
    private function availableAssetsQuery(int $productId, int $storeId, Carbon $from, Carbon $to): EloquentBuilder
    {
        // Correlated active-demand subquery: an asset is excluded when any active
        // demand claims it over a period overlapping the request.
        $conflicting = Demand::query()
            ->whereColumn('demands.asset_id', 'stock_levels.id')
            ->active()
            ->overlapping($from, $to);

        return StockLevel::query()
            ->forProduct($productId)
            ->forStore($storeId)
            ->serialized()
            ->whereNotExists($conflicting->getQuery())
            ->orderBy('id');
    }

    /**
     * Whether a specific serialised asset (stock level) is free for the entire
     * `[$from, $to)` window: true when no active demand claims it over an
     * overlapping period. Returns false if the stock level does not exist.
     */
    public function checkAssetAvailable(int $stockLevelId, Carbon $from, Carbon $to): bool
    {
        if (! StockLevel::query()->whereKey($stockLevelId)->exists()) {
            return false;
        }

        return ! Demand::query()
            ->where('asset_id', $stockLevelId)
            ->active()
            ->overlapping($from, $to)
            ->exists();
    }

    /**
     * The quantity of a product free for an opportunity line item over its whole
     * `[$from, $to)` window — the worst (most-constrained) slot — while excluding
     * that line item's OWN active demand so it is never counted short against its
     * own booking.
     *
     * This is the figure the shortage detector compares the line's requested
     * quantity against (shortage-resolution-sub-hires.md §2.1). Bulk and
     * serialised both reduce to "units free for others to claim, plus the units
     * this item itself already holds" — i.e. how many units this item could
     * fulfil. Computed live from demands, mirroring {@see checkAvailability()}.
     *
     * @param  string  $excludeSourceType  demand source_type to exclude (e.g. `opportunity_item`)
     * @param  int  $excludeSourceId  demand source_id to exclude (the line item id)
     */
    public function availableForItem(
        int $productId,
        int $storeId,
        Carbon $from,
        Carbon $to,
        string $excludeSourceType,
        int $excludeSourceId,
    ): int {
        $product = Product::query()->find($productId);

        if ($product === null) {
            return 0;
        }

        // Clamp an open-ended / sentinel-dated window to the rolling snapshot
        // horizon before generating slots (see checkAvailability()). The competing
        // demand set is still fetched against the ORIGINAL window so an indefinite
        // demand is seen across every clamped horizon slot — the worst slot is
        // therefore the correct answer for "units free for this item".
        [$slotFrom, $slotTo] = $this->clampToHorizon($from, $to);

        $timezone = $this->storeTimezone($storeId);
        $totalStock = $this->pipeline->totalStock($product, $storeId);

        /** @var Collection<int, Demand> $demands */
        $demands = Demand::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->overlapping($from, $to)
            ->where(static function ($query) use ($excludeSourceType, $excludeSourceId): void {
                // Exclude this item's own demand: everything else competes for stock.
                $query->where('source_type', '!=', $excludeSourceType)
                    ->orWhere('source_id', '!=', $excludeSourceId);
            })
            ->get();

        $worst = $totalStock;

        foreach ($this->slotCalculator->generateSlots($slotFrom, $slotTo, $timezone) as $slotStart) {
            $slotEnd = $this->slotCalculator->advance($slotStart, $timezone);
            [$demanded] = $this->sumDemandIn($demands, $slotStart, $slotEnd);

            $worst = min($worst, $totalStock - $demanded);
        }

        return $worst;
    }

    /**
     * Products in a store/range whose availability is negative.
     *
     * Reserved for the store-wide proactive shortage sweep (the listener path in
     * shortage-resolution-sub-hires.md §2.4). Opportunity-scoped detection — the
     * confirmation gate and the per-opportunity API badge — flows through
     * {@see ShortageDetector} and {@see availableForItem()}
     * instead. This store-wide variant lands with the proactive monitor; until
     * then it fails loudly rather than silently no-op.
     *
     * @return never
     */
    public function getShortages(int $storeId, Carbon $from, Carbon $to): mixed
    {
        throw new BadMethodCallException('Store-wide shortage sweeps are not implemented yet; use ShortageDetector for opportunity-scoped detection.');
    }

    /**
     * Composed availability for a catalogue (pool) kit, delegated to the
     * {@see KitAvailabilityCalculator}: per slot, MIN(floor(component_available /
     * component_qty)) across all pool components, reading component availability
     * from the snapshot range read and recursing for nested kits (depth-limited).
     *
     * Kits hold no snapshot rows and generate no demand of their own — this is the
     * only read path that produces a kit's availability.
     */
    public function getKitAvailability(int $productId, int $storeId, Carbon $from, Carbon $to): AvailabilityRangeData
    {
        return app(KitAvailabilityCalculator::class)->calculate($productId, $storeId, $from, $to);
    }

    /**
     * Whether a product is a catalogue kit (composed read-time from components).
     * Prefers the denormalised `is_kit` flag; only products flagged as kits are
     * routed to the kit calculator from the normal range read.
     */
    private function isKitProduct(int $productId): bool
    {
        return Product::query()->whereKey($productId)->where('is_kit', true)->exists();
    }

    /**
     * Sum active demand overlapping `[slotStart, slotEnd)` for a product/store,
     * with a per-source breakdown.
     *
     * @return array{0: int, 1: array<string, int>}
     */
    private function sumDemand(int $productId, int $storeId, Carbon $slotStart, Carbon $slotEnd): array
    {
        /** @var Collection<int, Demand> $demands */
        $demands = Demand::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->overlapping($slotStart, $slotEnd)
            ->get();

        return $this->sumDemandIn($demands, $slotStart, $slotEnd);
    }

    /**
     * Sum the demand in a pre-fetched collection overlapping a slot.
     *
     * Per-slot attribution uses the demand's BUFFERED bounds (turnaround/prep
     * baked in) — the same window the fetch overlaps on — so a unit is correctly
     * counted as occupied during its own prep/turnaround slots, matching the
     * Postgres `period &&` fetch on every driver.
     *
     * @param  Collection<int, Demand>  $demands
     * @return array{0: int, 1: array<string, int>}
     */
    private function sumDemandIn(Collection $demands, Carbon $slotStart, Carbon $slotEnd): array
    {
        $total = 0;
        $breakdown = [];

        foreach ($demands as $demand) {
            if (! ($demand->bufferedStartsAt()->lessThan($slotEnd) && $demand->bufferedEndsAt()->greaterThan($slotStart))) {
                continue;
            }

            $returned = max(0, (int) ($demand->metadata['returned_quantity'] ?? 0));
            $quantity = max(0, $demand->quantity - $returned);

            if ($quantity <= 0) {
                continue;
            }

            $total += $quantity;
            $breakdown[$demand->source_type] = ($breakdown[$demand->source_type] ?? 0) + $quantity;
        }

        return [$total, $breakdown];
    }

    /**
     * Clamp a live-read window to the rolling snapshot horizon before slot
     * generation, delegating to the pipeline so the read and write paths share
     * one horizon definition.
     *
     * An open-ended (sentinel-dated) demand window spans tens of thousands of
     * slots; left unclamped it trips the {@see SlotCalculator} safety cap. Slot
     * loops here only enumerate calendar slots — the competing demand set is
     * fetched against the caller's ORIGINAL window, so an indefinite demand is
     * still seen across every clamped slot and the worst-slot answer is unchanged.
     *
     * When the request lies entirely outside the horizon the clamp collapses
     * (`from >= to`); callers fall back to slotting the single `from` slot, which
     * the {@see SlotCalculator} handles, so the read never throws and never emits
     * an empty slot set.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function clampToHorizon(Carbon $from, Carbon $to): array
    {
        [$clampedFrom, $clampedTo] = $this->pipeline->clampToHorizon($from, $to);

        // A fully out-of-horizon window collapses to from >= to; keep the original
        // `from` so the slot loop still evaluates the slot containing it rather
        // than producing nothing.
        if (! $clampedFrom->lessThan($clampedTo)) {
            return [$from->copy(), $from->copy()];
        }

        return [$clampedFrom, $clampedTo];
    }

    /**
     * The IANA timezone for a store, falling back to the application timezone.
     */
    private function storeTimezone(int $storeId): ?string
    {
        return Store::query()->whereKey($storeId)->value('timezone');
    }
}

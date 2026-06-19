<?php

namespace App\Services\Availability;

use App\Contracts\DemandResolverContract;
use App\Enums\DemandPhase;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Translates opportunity line items into availability demand rows.
 *
 * The primary demand source: each opportunity line item generates its own
 * demand(s). The line item's phase follows the parent opportunity's status via
 * the **ceiling principle** — the opportunity status sets the maximum phase its
 * items may occupy ({@see OpportunityStatus::phase()}). Granular per-item
 * operational state tracking (Prepping/Dispatched/Returned/…) lands with the
 * item-mutation events in M3; until then the phase derives purely from the
 * opportunity status.
 *
 * Demand period = the item's effective dates (its own dates, inheriting the
 * opportunity's when null) with the product's before/after buffers baked in. A
 * missing end date is treated as indefinite (the sentinel).
 *
 * Serialised products with allocated assets produce one demand per asset
 * (`asset_id` set, quantity 1); bulk products — and serialised products with no
 * allocations yet — produce a single product-level demand (`asset_id` null,
 * quantity = line quantity).
 *
 * This resolver is a callable service. It is NOT wired to fire automatically on
 * item events (that wiring is M3); callers invoke {@see syncDemands()} /
 * {@see releaseDemands()} directly.
 */
class OpportunityItemDemandResolver implements DemandResolverContract
{
    public function sourceType(): string
    {
        return 'opportunity_item';
    }

    /**
     * The resolved availability context for a line item: the product it claims
     * against, the store (its `dispatch_store_id` override or the opportunity's
     * store), the effective demand window (date-source-aware, pre-buffer), and the
     * requested whole-unit quantity.
     *
     * Shared by the demand-writing path ({@see syncDemands()}) and the read path
     * ({@see App\Services\AvailabilityService::getOpportunityContext()}) so both
     * agree on which product/store/window/quantity a line resolves to. `productId`
     * / `storeId` are null when the line references no product or has no store.
     *
     * @return array{product_id: int|null, store_id: int|null, from: Carbon, to: Carbon, quantity: int}
     */
    public function resolveContext(OpportunityItem $item): array
    {
        $product = $this->resolveProduct($item);
        [$from, $to] = $this->resolveDates($item);

        return [
            'product_id' => $product?->id,
            'store_id' => $this->resolveStoreId($item),
            'from' => $from,
            'to' => $to,
            'quantity' => max(1, (int) round((float) $item->quantity)),
        ];
    }

    /**
     * Map the line item's parent opportunity status to a demand phase
     * (ceiling principle).
     */
    public function resolvePhase(Model $source): DemandPhase
    {
        $item = $this->asItem($source);

        return $item->opportunity->statusEnum()->phase();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildMetadata(Model $source): array
    {
        $item = $this->asItem($source);
        $opportunity = $item->opportunity;

        return [
            'opportunity_id' => $opportunity->id,
            'opportunity_state' => $opportunity->state->value,
            'opportunity_status' => $opportunity->status,
            'returned_quantity' => 0,
        ];
    }

    /**
     * Create or update the demand row(s) for the given line item. Idempotent:
     * re-syncing replaces the item's existing demands with the freshly-resolved
     * set, so it is safe to call repeatedly.
     */
    public function syncDemands(Model $source): void
    {
        $item = $this->asItem($source);

        $product = $this->resolveProduct($item);
        $storeId = $this->resolveStoreId($item);

        // Without a product or store there is nothing to claim against — clear
        // any stale demands and stop.
        if ($product === null || $storeId === null) {
            $this->releaseDemands($item);

            return;
        }

        $phase = $this->resolvePhase($item);
        $metadata = $this->buildMetadata($item);

        [$startsAt, $endsAt] = $this->resolveDates($item);

        // Turnaround/prep buffers only widen the window for phases that
        // physically occupy (or have just occupied) a unit; Draft/Void release
        // immediately with no buffer (availability-engine.md §"Turnaround Time").
        $appliesTurnaround = $phase->appliesTurnaround();

        [$bufferedStart, $bufferedEnd] = Demand::bufferedPeriod(
            $startsAt,
            $endsAt,
            $appliesTurnaround ? (int) ($product->buffer_before_minutes ?? 0) : 0,
            $appliesTurnaround ? (int) ($product->post_rent_unavailability ?? 0) : 0,
        );

        $allocatedAssetIds = $this->allocatedAssetIds($item);
        $isSerialised = $product->stock_method === StockMethod::Serialised && $allocatedAssetIds !== [];

        // Replace the item's demands wholesale so the resolver is idempotent and
        // converges (handles quantity changes, allocation changes, etc.).
        $this->purge($item);

        if ($isSerialised) {
            foreach ($allocatedAssetIds as $assetId) {
                $this->persist($product->id, $storeId, $assetId, 1, $startsAt, $endsAt, $bufferedStart, $bufferedEnd, $phase, $metadata, $item->id);
            }

            return;
        }

        $this->persist($product->id, $storeId, null, max(1, (int) round((float) $item->quantity)), $startsAt, $endsAt, $bufferedStart, $bufferedEnd, $phase, $metadata, $item->id);
    }

    /**
     * Re-sync every line item's demands after the parent opportunity's
     * state/status has transitioned.
     *
     * Item demands derive their phase from the parent opportunity status via the
     * ceiling principle, but they are written only by item events. When the
     * opportunity itself transitions (Quote → Order, → Lost/Dead/Cancelled/
     * Complete, or a status change within a state) the existing demand rows fall
     * out of step: an order's demands stay inactive, a dead deal's demands keep
     * blocking stock. This rebuilds them at the current ceiling phase.
     *
     *  - Closed (terminal) opportunity → void every item's demands.
     *  - Otherwise → rebuild every item's demands at the phase derived from the
     *    current status.
     *
     * Reuses {@see syncDemands()} / {@see releaseDemands()} so the phase logic is
     * not duplicated. The opportunity is re-fetched fresh on each item so the
     * resolved phase reflects the post-transition status, not a stale relation.
     */
    public function resyncForOpportunity(Opportunity $opportunity): void
    {
        $isClosed = $opportunity->statusEnum()->isClosed();

        $opportunity->items()->each(function (OpportunityItem $item) use ($opportunity, $isClosed): void {
            // Bind the freshly-loaded parent so resolvePhase()/buildMetadata()
            // read the post-transition status rather than a stale cached relation.
            $item->setRelation('opportunity', $opportunity);

            if ($isClosed) {
                $this->releaseDemands($item);

                return;
            }

            $this->syncDemands($item);
        });
    }

    /**
     * Release (void) all demands for the given line item.
     *
     * Demands are voided rather than deleted so the audit trail and any
     * downstream consumers retain the record; voided demands are inactive and
     * excluded from availability.
     */
    public function releaseDemands(Model $source): void
    {
        $item = $this->asItem($source);

        Demand::query()
            ->where('source_type', $this->sourceType())
            ->where('source_id', $item->id)
            ->update([
                'phase' => DemandPhase::Void->value,
                'is_active' => false,
            ]);
    }

    /**
     * Hard-remove the item's demands so {@see syncDemands()} can rebuild them.
     */
    protected function purge(OpportunityItem $item): void
    {
        Demand::query()
            ->where('source_type', $this->sourceType())
            ->where('source_id', $item->id)
            ->delete();
    }

    /**
     * Persist a single demand row, writing the PostgreSQL `period` range when on
     * that driver.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function persist(
        int $productId,
        int $storeId,
        ?int $assetId,
        int $quantity,
        Carbon $startsAt,
        Carbon $endsAt,
        Carbon $bufferedStart,
        Carbon $bufferedEnd,
        DemandPhase $phase,
        array $metadata,
        int $sourceId,
    ): void {
        $attributes = [
            'product_id' => $productId,
            'store_id' => $storeId,
            'asset_id' => $assetId,
            'quantity' => $quantity,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            // Snapshot the buffered window on every driver so per-slot PHP
            // attribution and the SQLite overlap path agree with the Postgres
            // `period &&` fetch. Snapshotted (not recomputed on read) for
            // replay-stability.
            'buffered_starts_at' => $bufferedStart,
            'buffered_ends_at' => $bufferedEnd,
            'source_type' => $this->sourceType(),
            'source_id' => $sourceId,
            'phase' => $phase->value,
            'is_active' => $phase->isActive(),
            'priority' => 0,
            'metadata' => $metadata,
        ];

        $demand = new Demand($attributes);

        if ($demand->getConnection()->getDriverName() === 'pgsql') {
            // The `period` column exists only on PostgreSQL. Set it through the
            // raw expression so the tstzrange is built server-side.
            $demand->setRawAttributes(
                array_merge($demand->getAttributes(), [
                    'period' => Demand::periodExpression($bufferedStart, $bufferedEnd),
                ]),
                true,
            );
            $demand->fill($attributes);
        }

        $demand->save();
    }

    /**
     * Resolve the effective demand window for a line item, inheriting the
     * opportunity's dates when the item's own dates are null. A missing end is
     * treated as indefinite (the sentinel).
     *
     * The date pair is selected by the `availability.demand_date_source` setting
     * (availability-engine.md §"Configurable Availability Window"):
     *
     *  - `operational` (default) — the line item's `starts_at` / `ends_at`,
     *    inheriting the opportunity's operational dates when null.
     *  - `charge` — the opportunity's billing window
     *    (`charge_starts_at` / `charge_ends_at`). Line items carry no charge-date
     *    fields yet, so the charge window is taken from the opportunity directly
     *    and falls back to the operational window when a charge bound is unset.
     *    (Item-level charge dates land with the dispatch/return model — see M5.)
     *
     * @return array{0: Carbon, 1: Carbon} [startsAt, endsAt]
     */
    protected function resolveDates(OpportunityItem $item): array
    {
        $opportunity = $item->opportunity;

        $operationalStart = $item->starts_at ?? $opportunity->starts_at ?? Carbon::now();
        $operationalEnd = $item->ends_at ?? $opportunity->ends_at ?? Demand::sentinel();

        if ($this->demandDateSource() === 'charge') {
            // Item-level charge dates do not exist yet; the charge window is the
            // opportunity's billing period, falling back to operational dates
            // when a charge bound is unset.
            // charge-date item fields pending — see M5.
            $startsAt = $opportunity->charge_starts_at ?? $operationalStart;
            $endsAt = $opportunity->charge_ends_at ?? $operationalEnd;

            return [Carbon::parse($startsAt), Carbon::parse($endsAt)];
        }

        return [
            Carbon::parse($operationalStart),
            Carbon::parse($operationalEnd),
        ];
    }

    /**
     * The configured demand date source — `operational` (default) or `charge`,
     * read from the `availability.demand_date_source` system setting. Any
     * unrecognised value falls back to `operational`.
     */
    protected function demandDateSource(): string
    {
        $source = (string) settings('availability.demand_date_source', 'operational');

        return $source === 'charge' ? 'charge' : 'operational';
    }

    /**
     * The store a line item's demand claims against.
     *
     * A line may override the opportunity's primary store with its own
     * `dispatch_store_id` (multi-warehouse dispatch per line,
     * availability-engine.md §"Multi-warehouse dispatch per line"); when unset it
     * inherits the opportunity's `store_id`. Returns null only when neither is
     * set, in which case the caller releases any stale demands and stops.
     */
    protected function resolveStoreId(OpportunityItem $item): ?int
    {
        return $item->dispatch_store_id ?? $item->opportunity->store_id;
    }

    /**
     * The allocated serialised asset (stock level) ids for the line item.
     *
     * @return list<int>
     */
    protected function allocatedAssetIds(OpportunityItem $item): array
    {
        return $item->assets()
            ->whereNotNull('stock_level_id')
            ->pluck('stock_level_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Resolve the catalogue product a line item refers to.
     *
     * Line items carry a polymorphic catalogue reference (`item_type`/
     * `item_id`). Only product-backed items generate demands; everything else
     * (services, ad-hoc lines) resolves to null and creates no demand.
     */
    protected function resolveProduct(OpportunityItem $item): ?Product
    {
        if ($item->item_id === null || ! $this->referencesProduct($item->item_type)) {
            return null;
        }

        return Product::query()->find($item->item_id);
    }

    /**
     * Whether the given polymorphic type refers to a product. Accepts the model
     * FQN and the short `product` morph alias.
     */
    protected function referencesProduct(?string $type): bool
    {
        if ($type === null) {
            return false;
        }

        return $type === Product::class || strtolower($type) === 'product';
    }

    /**
     * Narrow the contract's generic Model to an OpportunityItem.
     *
     * @throws InvalidArgumentException when given the wrong model type
     */
    protected function asItem(Model $source): OpportunityItem
    {
        if (! $source instanceof OpportunityItem) {
            throw new InvalidArgumentException(
                'OpportunityItemDemandResolver expects an OpportunityItem, got '.$source::class
            );
        }

        return $source;
    }
}

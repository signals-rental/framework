<?php

namespace App\Services\Availability;

use App\Contracts\DemandResolverContract;
use App\Enums\AssetAssignmentStatus;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\DemandPhase;
use App\Enums\KitComponentBinding;
use App\Enums\ReleasePoint;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\SerialisedComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Translates opportunity line items into availability demand rows.
 *
 * The primary demand source: each opportunity line item generates its own
 * demand(s). The line item's phase follows the parent opportunity's status via
 * the **ceiling principle** — the opportunity status sets the maximum phase its
 * items may occupy ({@see OpportunityStatus::phase()}). Granular per-asset
 * operational tracking (Allocated/Prepared/Dispatched/Returned) is wired in M5 via
 * the per-asset assignment events: an allocated asset's demand window follows its
 * actual dispatch/return milestones ({@see assetOperationalWindow()}), while the
 * line-level phase still derives from the parent opportunity status.
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
 * Kit lines branch by kit type (availability-engine.md §"Kit Type Reference"):
 * catalogue (pool) kits explode — the kit product generates no demand of its own
 * and each POOL component produces a demand against the COMPONENT product at
 * (line_qty × component_qty); serialised-permanent (container kit-mode) kits book
 * the housing as a normal serialised/bulk unit (fixed components are held by their
 * standing container demands, not re-exploded); hybrid kits do both — explode pool
 * components AND claim the housing. See {@see syncKitComponentDemands()}.
 *
 * This resolver is a callable service. Item-mutation and per-asset assignment
 * events (M5) re-sync a line's demands through {@see syncDemands()} after a change;
 * opportunity-level transitions re-sync every line via
 * {@see resyncForOpportunity()}. Callers may also invoke {@see syncDemands()} /
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
     *
     * The configurable {@see ReleasePoint} (availability.release_point setting) is
     * read HERE — on the resolver/handle path, never inside an event's pure
     * apply() — so the Operational → Closed boundary stays replay-safe and honours
     * the tenant setting.
     */
    public function resolvePhase(Model $source): DemandPhase
    {
        $item = $this->asItem($source);

        return $item->opportunity->statusEnum()->phase($this->releasePoint());
    }

    /**
     * The configured demand release point, read from the
     * `availability.release_point` system setting. Any unrecognised/absent value
     * falls back to the {@see ReleasePoint::default()} (Returned), preserving the
     * historical close-on-return behaviour.
     */
    protected function releasePoint(): ReleasePoint
    {
        $value = (string) settings('availability.release_point', ReleasePoint::default()->value);

        return ReleasePoint::tryFrom($value) ?? ReleasePoint::default();
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
            'returned_quantity' => (int) round((float) ($item->returned_quantity ?? '0')),
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

        $allocatedAssets = $this->allocatedAssets($item);
        $isSerialised = $product->stock_method === StockMethod::Serialised && $allocatedAssets !== [];

        $quantity = max(1, (int) round((float) $item->quantity));

        // Replace the item's demands wholesale so the resolver is idempotent and
        // converges (handles quantity changes, allocation changes, etc.).
        $this->purge($item);

        $containerMode = $product->container_availability_mode;

        // Kit line handling depends on the kit type (availability-engine.md
        // §"Kit Type Reference"):
        //
        //  - Serialised-permanent (container_availability_mode = kit): the kit
        //    CONTAINER product is the bookable entity — book it as a normal
        //    serialised/bulk unit (fall through). Fixed components are already held
        //    by their indefinite container demands, so they do NOT re-explode here.
        //  - Hybrid (container_availability_mode = hybrid): the housing is booked as
        //    a serialised unit AND each POOL component explodes into its own demand;
        //    FIXED components stay container-held (not re-exploded). So we explode
        //    the pool side here and ALSO fall through to claim the housing.
        //  - Catalogue (pool) kit (no container mode): the kit product generates NO
        //    demand of its own — every POOL component explodes at (line_qty ×
        //    component_qty), drawing the components from general stock. Return after
        //    exploding (no housing to claim).
        if ($product->isKit() && $containerMode === null) {
            $this->syncKitComponentDemands(
                $product,
                $item,
                $storeId,
                $quantity,
                $startsAt,
                $endsAt,
                $bufferedStart,
                $bufferedEnd,
                $phase,
                $metadata,
            );

            return;
        }

        if ($containerMode === ContainerAvailabilityMode::Hybrid) {
            // Pool components draw from general stock per dispatch — explode them.
            // Fixed components are container-held (skipped inside the method via the
            // binding filter). The housing itself is then claimed below as a normal
            // serialised/bulk unit.
            $this->syncKitComponentDemands(
                $product,
                $item,
                $storeId,
                $quantity,
                $startsAt,
                $endsAt,
                $bufferedStart,
                $bufferedEnd,
                $phase,
                $metadata,
            );
        }

        if ($isSerialised) {
            // Serialised demand transition (opportunity-lifecycle.md §9.3): each
            // allocated asset claims a specific unit (asset_id set, quantity 1),
            // while any still-unallocated remainder keeps a single quantity-based
            // demand (asset_id null) so the line continues to claim the units it
            // has not yet assigned to a physical asset. When the line is fully
            // allocated the remainder is zero and no residual demand is written.
            //
            // Each asset's demand follows OPERATIONAL reality, not just the planned
            // line dates (availability-engine.md "Asset-Level Date Tracking"): an
            // asset dispatched before the planned start pulls its demand start back
            // to the actual dispatch time, and a returned asset's demand ends at the
            // actual return time (phase Closed) with turnaround off that return. The
            // dates live on the projected assignment row, so this reproduces across
            // any resync AND a Verbs replay (the projection is replay-stable).
            foreach ($allocatedAssets as $asset) {
                [$assetStart, $assetEnd, $assetPhase] = $this->assetOperationalWindow(
                    $asset,
                    $startsAt,
                    $endsAt,
                    $phase,
                );

                [$assetBufferedStart, $assetBufferedEnd] = Demand::bufferedPeriod(
                    $assetStart,
                    $assetEnd,
                    $assetPhase->appliesTurnaround() ? (int) ($product->buffer_before_minutes ?? 0) : 0,
                    $assetPhase->appliesTurnaround() ? (int) ($product->post_rent_unavailability ?? 0) : 0,
                );

                $this->persist($product->id, $storeId, (int) $asset->stock_level_id, 1, $assetStart, $assetEnd, $assetBufferedStart, $assetBufferedEnd, $assetPhase, $metadata, $item->id);
            }

            $remainder = $quantity - count($allocatedAssets);

            if ($remainder > 0) {
                $this->persist($product->id, $storeId, null, $remainder, $startsAt, $endsAt, $bufferedStart, $bufferedEnd, $phase, $metadata, $item->id);
            }

            return;
        }

        // Bulk lines: the effective demanded quantity is the requested quantity
        // minus whatever has already been returned (opportunity-lifecycle.md §5.5 /
        // availability-engine.md "Partial returns for bulk items"). A fully-returned
        // line drops to zero and writes no demand.
        $effectiveQuantity = max(0, $quantity - (int) round((float) ($item->returned_quantity ?? '0')));

        if ($effectiveQuantity <= 0) {
            return;
        }

        $this->persist($product->id, $storeId, null, $effectiveQuantity, $startsAt, $endsAt, $bufferedStart, $bufferedEnd, $phase, $metadata, $item->id);
    }

    /**
     * Explode a catalogue (pool) kit line into one demand per pool component.
     *
     * Each pool component claims (line_qty × component.quantity) units of the
     * COMPONENT product (rounded up to whole units — demands are integer), at the
     * line's window with the COMPONENT's own before/after buffers baked in (the kit
     * parent carries no meaningful buffers of its own). All demands share the line
     * item's `source_type`/`source_id` so {@see purge()} / {@see releaseDemands()}
     * reverse them as one set, and so a resync converges.
     *
     * FIXED-binding components are deliberately skipped here — they are
     * permanently container-bound and held by their standing
     * `source_type = 'container'` demands (created when the item is packed,
     * {@see ContainerDemandResolver}), so they must NOT be re-exploded per booking.
     * This is the filled M5-3b seam: fixed routes to container demands, pool
     * explodes.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function syncKitComponentDemands(
        Product $kit,
        OpportunityItem $item,
        int $storeId,
        int $kitQuantity,
        Carbon $startsAt,
        Carbon $endsAt,
        Carbon $bufferedStart,
        Carbon $bufferedEnd,
        DemandPhase $phase,
        array $metadata,
    ): void {
        $appliesTurnaround = $phase->appliesTurnaround();

        /** @var list<SerialisedComponent> $components */
        $components = $kit->components()
            ->where('binding', KitComponentBinding::Pool->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();

        foreach ($components as $component) {
            // Fixed components are container-held (their availability is removed via
            // standing container demands), so they are never exploded per booking —
            // only pool components draw from general stock here.
            if ($component->binding !== KitComponentBinding::Pool) {
                continue;
            }

            // Whole units required of this component: line qty × per-kit qty, never
            // below 1 for a present component.
            $componentQuantity = max(1, (int) ceil($kitQuantity * (float) $component->quantity));

            $componentProduct = Product::query()->find($component->component_product_id);

            if ($componentProduct === null) {
                continue;
            }

            // A component that is itself a kit recurses one level: build a synthetic
            // item context is overkill here — instead explode its pool components
            // against the same line, scaling quantities. Keep it simple by treating
            // nested kit components as their own explosion via this same method.
            if ($componentProduct->isKit()) {
                $this->syncKitComponentDemands(
                    $componentProduct,
                    $item,
                    $storeId,
                    $componentQuantity,
                    $startsAt,
                    $endsAt,
                    $bufferedStart,
                    $bufferedEnd,
                    $phase,
                    $metadata,
                );

                continue;
            }

            [$componentBufferedStart, $componentBufferedEnd] = Demand::bufferedPeriod(
                $startsAt,
                $endsAt,
                $appliesTurnaround ? (int) ($componentProduct->buffer_before_minutes ?? 0) : 0,
                $appliesTurnaround ? (int) ($componentProduct->post_rent_unavailability ?? 0) : 0,
            );

            $this->persist(
                $componentProduct->id,
                $storeId,
                null,
                $componentQuantity,
                $startsAt,
                $endsAt,
                $componentBufferedStart,
                $componentBufferedEnd,
                $phase,
                $metadata,
                $item->id,
            );
        }
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

        // VERSION INVARIANT: only the ACTIVE version's items hold demands. When the
        // opportunity carries versions, release demands for every line that is NOT
        // in the active version (a superseded/alternative version's items must not
        // block stock), then (re)sync the active version's lines below.
        if ($opportunity->active_version_id > 0) {
            $opportunity->allItems()
                ->where('version_id', '!=', $opportunity->active_version_id)
                ->each(function (OpportunityItem $item): void {
                    $this->releaseDemands($item);
                });
        }

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
     * The allocated serialised asset assignment rows for the line item (carrying
     * status + actual dispatch/return timestamps so the per-asset demand window can
     * follow operational reality).
     *
     * @return list<OpportunityItemAsset>
     */
    protected function allocatedAssets(OpportunityItem $item): array
    {
        return $item->assets()
            ->whereNotNull('stock_level_id')
            ->get()
            ->all();
    }

    /**
     * Resolve a single allocated asset's operational demand window from its planned
     * line window and its actual dispatch/return milestones
     * (availability-engine.md "Asset-Level Date Tracking").
     *
     *  - Returned (CheckedIn/Finalised): the window closes at the actual return time
     *    and the phase is forced to Closed (inactive) — the unit is physically back,
     *    regardless of the line's ceiling phase.
     *  - Dispatched/OnHire before the planned start: the start is pulled back to the
     *    actual dispatch time.
     *  - Otherwise the planned line window + ceiling phase apply unchanged.
     *
     * @return array{0: Carbon, 1: Carbon, 2: DemandPhase}
     */
    protected function assetOperationalWindow(
        OpportunityItemAsset $asset,
        Carbon $plannedStart,
        Carbon $plannedEnd,
        DemandPhase $linePhase,
    ): array {
        $status = $asset->status;

        // A returned asset is physically back: close its demand at the actual return.
        if (
            in_array($status, [AssetAssignmentStatus::CheckedIn, AssetAssignmentStatus::Finalised], true)
            && $asset->returned_at !== null
        ) {
            return [$plannedStart, Carbon::parse($asset->returned_at), DemandPhase::Closed];
        }

        // An asset dispatched before its planned start pulls the start back to the
        // actual dispatch time.
        if (
            in_array($status, [AssetAssignmentStatus::Dispatched, AssetAssignmentStatus::OnHire], true)
            && $asset->dispatched_at !== null
        ) {
            $actualDispatch = Carbon::parse($asset->dispatched_at);

            if ($actualDispatch->lessThan($plannedStart)) {
                return [$actualDispatch, $plannedEnd, $linePhase];
            }
        }

        return [$plannedStart, $plannedEnd, $linePhase];
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

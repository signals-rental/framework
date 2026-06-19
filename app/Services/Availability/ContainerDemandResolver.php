<?php

namespace App\Services\Availability;

use App\Contracts\DemandResolverContract;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\DemandPhase;
use App\Enums\KitComponentBinding;
use App\Models\ContainerItem;
use App\Models\Demand;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Translates a container membership ({@see ContainerItem}) into an availability
 * demand for the packed serialised item.
 *
 * When a serialised item is packed into a KIT-mode container — or into a
 * HYBRID-mode container as a FIXED-binding component — it is removed from
 * individual availability: an INDEFINITE demand
 * (`asset_id = stock_level_id`, `quantity = 1`, `ends_at = sentinel`,
 * `source_type = 'container'`, `source_id = container_items.id`) holds the unit
 * for as long as it remains packed (serialised-containers.md §"Kit Mode —
 * Contents Removed from Availability").
 *
 * Transport-mode containers create NO container demands — their contents stay
 * individually available until dispatch-dissolve (Phase 4). Hybrid pool-binding
 * components likewise stay available; they receive ordinary `opportunity_item`
 * demands when the kit is dispatched.
 *
 * **Phase choice.** Container reservations use {@see DemandPhase::Committed} —
 * the only indefinite, always-active phase that fits a standing reservation. The
 * item is genuinely committed to the kit (not Draft/provisional, not
 * Operational/in-transit, not Closed/returned), so Committed correctly removes it
 * from individual availability while it is packed. Turnaround does NOT apply
 * (Committed, not Closed), which is right: a packed item has no return-processing
 * window — it is simply unavailable until physically unpacked.
 *
 * Containers are plain Eloquent (not event-sourced), so demand writes happen
 * directly here with no Verbs replay concern; callers ({@see PackContainerItem} /
 * {@see UnpackContainerItem}) wrap the mutation in a transaction.
 */
class ContainerDemandResolver implements DemandResolverContract
{
    public function sourceType(): string
    {
        return 'container';
    }

    /**
     * Create (or converge) the container demand for a membership row.
     *
     * A demand is written only when the membership's container holds its contents
     * from availability for THIS item — kit mode (all members) or hybrid mode
     * with a fixed binding for the item's product. Otherwise any stale demand for
     * the row is released and nothing new is written (transport / hybrid-pool).
     *
     * Idempotent: the row's existing container demands are purged and rebuilt, so
     * re-syncing converges (status changes, store moves, etc.).
     */
    public function syncDemands(Model $source): void
    {
        $item = $this->asContainerItem($source);

        // An inactive (unpacked) membership claims nothing — release and stop.
        if (! $item->isActive()) {
            $this->releaseDemands($item);

            return;
        }

        if (! $this->shouldHoldFromAvailability($item)) {
            // Transport, or a hybrid pool component — no container demand. Clear
            // any stale demand so a mode/binding change converges.
            $this->purge($item);

            return;
        }

        $context = $this->resolveContext($item);

        if ($context === null) {
            $this->purge($item);

            return;
        }

        [$productId, $storeId] = $context;

        $this->purge($item);

        $phase = $this->resolvePhase($item);
        // `packed_at` is cast to an immutable Carbon; the demand window helpers
        // expect the mutable Illuminate Carbon, so normalise it here.
        $startsAt = Carbon::parse($item->packed_at->toIso8601String());
        $endsAt = Demand::sentinel();

        // An indefinite reservation: no turnaround/prep buffer — the unit is held
        // exactly while packed, releasing the instant it is unpacked.
        [$bufferedStart, $bufferedEnd] = Demand::bufferedPeriod($startsAt, $endsAt, 0, 0);

        $attributes = [
            'product_id' => $productId,
            'store_id' => $storeId,
            'asset_id' => $item->serialised_item_id,
            'quantity' => 1,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'buffered_starts_at' => $bufferedStart,
            'buffered_ends_at' => $bufferedEnd,
            'source_type' => $this->sourceType(),
            'source_id' => $item->id,
            'phase' => $phase->value,
            'is_active' => $phase->isActive(),
            'priority' => 0,
            'metadata' => $this->buildMetadata($item),
        ];

        $demand = new Demand($attributes);

        if ($demand->getConnection()->getDriverName() === 'pgsql') {
            // The `period` tstzrange exists only on PostgreSQL; build it server-side.
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
     * Purge (delete) all container demands for a membership row on
     * unpack/transfer/dissolve.
     *
     * Unlike opportunity-item demands — which are VOIDED to retain an in-row audit
     * trail — a container membership's audit lives on the membership itself
     * (`container_items.unpacked_at` / `unpacked_reason`), not on the demand. Each
     * re-pack mints a NEW `source_id` (a new membership row), so a voided demand
     * from a prior pack is never reclaimed and would accumulate unbounded across
     * pack → unpack → re-pack cycles. Deleting on release keeps the `demands` table
     * bounded and leaves no dead rows for the Postgres exclusion constraint to
     * consider.
     */
    public function releaseDemands(Model $source): void
    {
        $item = $this->asContainerItem($source);

        $this->purge($item);
    }

    /**
     * A packed container reservation is Committed: a standing, active claim that
     * removes the unit from individual availability for as long as it is packed.
     */
    public function resolvePhase(Model $source): DemandPhase
    {
        $item = $this->asContainerItem($source);

        return $item->isActive() ? DemandPhase::Committed : DemandPhase::Void;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildMetadata(Model $source): array
    {
        $item = $this->asContainerItem($source);

        return [
            'container_id' => $item->container_id,
            'container_item_id' => $item->id,
            'serialised_item_id' => $item->serialised_item_id,
        ];
    }

    /**
     * Whether the membership's container removes THIS item from individual
     * availability:
     *
     *  - Kit mode → yes, every member is held.
     *  - Hybrid mode → only when the item's product is a FIXED-binding slot in the
     *    container product's composition; pool slots stay available.
     *  - Transport mode (or no backing product) → no.
     */
    protected function shouldHoldFromAvailability(ContainerItem $item): bool
    {
        $container = $item->container;

        if ($container === null) {
            return false;
        }

        $mode = $container->availabilityMode();

        if ($mode === ContainerAvailabilityMode::Kit) {
            return true;
        }

        if ($mode === ContainerAvailabilityMode::Transport) {
            return false;
        }

        // Hybrid: hold only if the packed item's product is a fixed-binding slot
        // of the container product.
        return $this->isFixedSlot($item);
    }

    /**
     * Whether the packed item's product is declared as a FIXED-binding slot of the
     * container product's composition (hybrid mode). Pool slots return false.
     */
    protected function isFixedSlot(ContainerItem $item): bool
    {
        $container = $item->container;

        if ($container?->product === null) {
            return false;
        }

        return $container->product->components()
            ->where('component_product_id', $item->product_id)
            ->where('binding', KitComponentBinding::Fixed->value)
            ->exists();
    }

    /**
     * Resolve the (product_id, store_id) the container demand claims against: the
     * packed item's own product and its store. Returns null when the stock level
     * is missing (the demand cannot be placed).
     *
     * @return array{0: int, 1: int}|null
     */
    protected function resolveContext(ContainerItem $item): ?array
    {
        $stockLevel = StockLevel::query()->find($item->serialised_item_id);

        if ($stockLevel === null) {
            return null;
        }

        return [(int) $item->product_id, (int) $stockLevel->store_id];
    }

    /**
     * Hard-remove the membership's container demands so {@see syncDemands()} can
     * rebuild them.
     */
    protected function purge(ContainerItem $item): void
    {
        Demand::query()
            ->where('source_type', $this->sourceType())
            ->where('source_id', $item->id)
            ->delete();
    }

    /**
     * Narrow the contract's generic Model to a ContainerItem.
     *
     * @throws InvalidArgumentException when given the wrong model type
     */
    protected function asContainerItem(Model $source): ContainerItem
    {
        if (! $source instanceof ContainerItem) {
            throw new InvalidArgumentException(
                'ContainerDemandResolver expects a ContainerItem, got '.$source::class
            );
        }

        return $source;
    }
}

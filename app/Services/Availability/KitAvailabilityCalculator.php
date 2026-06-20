<?php

namespace App\Services\Availability;

use App\Data\Availability\AvailabilityRangeData;
use App\Data\Availability\AvailabilitySlotData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\KitComponentBinding;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Services\AvailabilityService;
use Closure;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Read-time availability for catalogue (non-serialised / pool) kits.
 *
 * A kit holds NO snapshot rows and generates NO demand of its own
 * (availability-engine.md §"Non-Serialised Kits"). Its availability is composed
 * at read time from its components:
 *
 *     kit_available(slot) = MIN over components of
 *                           floor(component_available(slot) / component.quantity)
 *
 * across every slot in the requested range. Component availability is read from
 * the pre-calculated snapshot range read ({@see AvailabilityService::getAvailabilityRange()})
 * — NOT live `demands` — so component availability already reflects ALL demands
 * (other kits, standalone rentals, quarantines, …), avoiding an N×M demand query
 * explosion. A component that is itself a kit recurses (depth-limited).
 *
 * Three kit types are computed here (availability-engine.md §"Kit Type
 * Reference"), branched on the product's `container_availability_mode`:
 *
 *  - **Catalogue / pool kit** (no container mode) — the M5-3a MIN formula over
 *    POOL components. FIXED components are not container-backed here, so they are
 *    skipped (they have no demand of their own in a pure catalogue kit).
 *  - **Serialised-permanent kit** (`container_availability_mode = kit`) — the kit
 *    CONTAINER product is the bookable entity; its availability is the serialised
 *    availability of the housing (is a kit container free for the window?). Fixed
 *    components are held indefinitely by `source_type = 'container'` demands, so
 *    they do not re-enter the per-booking calculation — but a fixed component
 *    blocked by a NON-container demand makes the kit unfulfillable (clamps to 0).
 *  - **Hybrid kit** (`container_availability_mode = hybrid`) — MIN(fixed-ok ?
 *    housing-availability : 0, pool MIN): fixed slots checked via container
 *    demands (housing availability), pool slots via the standard MIN formula.
 *
 * The service is resolved lazily through a closure so it is Octane-safe (never
 * captures a request-bound singleton in a constructor) and mockable in tests.
 */
class KitAvailabilityCalculator
{
    /**
     * @param  Closure(): AvailabilityService  $availabilityService  lazy resolver for the read service
     */
    public function __construct(
        private readonly Closure $availabilityService,
    ) {}

    /**
     * Compose a kit's per-slot availability over `[$from, $to]` from its
     * components. Returns a bounded {@see AvailabilityRangeData} whose slots align
     * to the component snapshot slots; `available` per slot is the floored MIN
     * ratio across all pool components.
     *
     * @throws RuntimeException when the kit nesting exceeds the configured max depth
     */
    public function calculate(int $kitProductId, int $storeId, Carbon $from, Carbon $to): AvailabilityRangeData
    {
        $product = Product::query()->find($kitProductId);
        $mode = $product?->container_availability_mode;

        // Serialised-permanent / hybrid container kits compute differently — the
        // kit container product is the bookable entity. Catalogue (pool) kits fall
        // through to the M5-3a composition.
        if ($mode === ContainerAvailabilityMode::Kit) {
            return $this->calculateSerialisedPermanent($kitProductId, $storeId, $from, $to);
        }

        if ($mode === ContainerAvailabilityMode::Hybrid) {
            return $this->calculateHybrid($kitProductId, $storeId, $from, $to);
        }

        $slots = $this->computeSlots($kitProductId, $storeId, $from, $to, 1, [$kitProductId]);

        return AvailabilityRangeData::make($kitProductId, $storeId, $from, $to, $slots, null);
    }

    /**
     * Serialised-permanent (kit-mode container) availability over the range.
     *
     * The kit container product is the bookable entity: per slot the kit's
     * availability is the housing's own serialised availability (free kit
     * containers for that window), read from the housing's pre-calculated snapshot
     * range. Fixed components are already held by their indefinite container
     * demands and so do not re-enter the calc — but if a fixed component is blocked
     * by a NON-container demand (e.g. it was individually booked before being
     * packed), the kit is unfulfillable for that slot and clamps to 0.
     */
    private function calculateSerialisedPermanent(int $kitProductId, int $storeId, Carbon $from, Carbon $to): AvailabilityRangeData
    {
        $housing = $this->housingAvailabilityBySlot($kitProductId, $storeId, $from, $to);

        // A fixed-component conflict (a non-container demand on a fixed member)
        // makes the kit unfulfillable across the window — clamp every slot to 0.
        $fixedConflicted = $this->hasFixedComponentConflict($kitProductId, $storeId, $from, $to);

        $slots = [];

        foreach ($housing as $slotKey => $available) {
            $slots[] = new AvailabilitySlotData(
                slot_start: $slotKey,
                total_stock: 0,
                total_demanded: 0,
                available: $fixedConflicted ? 0 : max(0, $available),
                demand_breakdown: [],
                pending_checkin_quantity: 0,
            );
        }

        return AvailabilityRangeData::make($kitProductId, $storeId, $from, $to, $slots, null);
    }

    /**
     * Hybrid container availability over the range:
     *
     *     kit_available(slot) = MIN(
     *         fixed-ok ? housing_available(slot) : 0,    // fixed slots via container
     *         pool_MIN(slot)                              // pool slots via M5-3a
     *     )
     *
     * Fixed slots are held by container demands, so their constraint is the
     * housing availability (gated to 0 by any fixed-component non-container
     * conflict). Pool slots are drawn from general stock per dispatch and use the
     * standard MIN(floor(component_available / qty)) composition.
     */
    private function calculateHybrid(int $kitProductId, int $storeId, Carbon $from, Carbon $to): AvailabilityRangeData
    {
        $housing = $this->housingAvailabilityBySlot($kitProductId, $storeId, $from, $to);
        $fixedConflicted = $this->hasFixedComponentConflict($kitProductId, $storeId, $from, $to);

        // Pool side: the M5-3a composition (only POOL components participate).
        $poolSlots = $this->computeSlots($kitProductId, $storeId, $from, $to, 1, [$kitProductId]);

        // Whether this hybrid kit has ANY pool components. With no pool side the kit
        // is housing-only and the housing value stands; with a pool side present, a
        // slot the pool does not report is a coverage gap → unfulfillable (clamp 0),
        // matching the catalogue {@see computeSlots()} convention.
        $hasPoolComponents = $this->hasPoolComponents($kitProductId);

        /** @var array<string, int> $poolBySlot */
        $poolBySlot = [];

        foreach ($poolSlots as $slot) {
            $poolBySlot[$slot->slot_start] = $slot->available;
        }

        // The slot universe is the housing slots (the kit container drives the
        // bookable window); each is constrained by the pool MIN when present.
        $slots = [];

        foreach ($housing as $slotKey => $housingAvailable) {
            $fixedComponent = $fixedConflicted ? 0 : max(0, $housingAvailable);

            // Pool-side constraint for the slot:
            //  - no pool components at all → housing-only, pool does not limit;
            //  - pool components present and this slot reported → use the pool MIN;
            //  - pool components present but this slot missing → coverage gap,
            //    the kit is unfulfillable here → clamp to 0.
            if (! $hasPoolComponents) {
                $poolComponent = $fixedComponent;
            } elseif (array_key_exists($slotKey, $poolBySlot)) {
                $poolComponent = max(0, $poolBySlot[$slotKey]);
            } else {
                $poolComponent = 0;
            }

            $slots[] = new AvailabilitySlotData(
                slot_start: $slotKey,
                total_stock: 0,
                total_demanded: 0,
                available: min($fixedComponent, $poolComponent),
                demand_breakdown: [],
                pending_checkin_quantity: 0,
            );
        }

        return AvailabilityRangeData::make($kitProductId, $storeId, $from, $to, $slots, null);
    }

    /**
     * The kit container housing's own serialised availability per slot, keyed by
     * the slot's ISO start — read from the housing product's pre-calculated
     * snapshot range (the kit container product is a normal serialised tracked
     * product with snapshot rows).
     *
     * @return array<string, int>
     */
    private function housingAvailabilityBySlot(int $kitProductId, int $storeId, Carbon $from, Carbon $to): array
    {
        // Read the housing product's RAW snapshots — never the kit-routed read —
        // so a container kit product does not recurse back into composed-product
        // routing.
        $range = ($this->availabilityService)()->rawSnapshotRange($kitProductId, $storeId, $from, $to);

        $bySlot = [];

        foreach ($range->slots as $slot) {
            $bySlot[$slot->slot_start] = $slot->available;
        }

        return $bySlot;
    }

    /**
     * Whether the kit declares any POOL-binding components — distinguishing a
     * housing-only hybrid kit (pool does not constrain) from one whose pool side
     * has a coverage gap for a slot (unfulfillable → clamp to 0).
     */
    private function hasPoolComponents(int $kitProductId): bool
    {
        return SerialisedComponent::query()
            ->where('product_id', $kitProductId)
            ->where('binding', KitComponentBinding::Pool->value)
            ->exists();
    }

    /**
     * Whether any FIXED-binding component of the kit is blocked by a demand that
     * is NOT its own container demand — i.e. it was booked individually before
     * (or instead of) being packed, so the kit cannot be fulfilled as a unit
     * (serialised-containers.md §"Conflict Handling").
     *
     * Fixed components are normally held only by their `source_type = 'container'`
     * demand; any OTHER active demand overlapping the window on the fixed
     * component's product is a genuine conflict.
     */
    private function hasFixedComponentConflict(int $kitProductId, int $storeId, Carbon $from, Carbon $to): bool
    {
        $service = ($this->availabilityService)();

        /** @var list<SerialisedComponent> $fixed */
        $fixed = SerialisedComponent::query()
            ->where('product_id', $kitProductId)
            ->where('binding', KitComponentBinding::Fixed->value)
            ->get()
            ->all();

        foreach ($fixed as $component) {
            if (! $service->fixedComponentSatisfied(
                $component->component_product_id,
                $storeId,
                $from,
                $to,
                (int) ceil((float) $component->quantity),
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively compose per-slot availability for a (possibly nested) kit.
     *
     * Returns a list of {@see AvailabilitySlotData}, one per component snapshot
     * slot, with `available` = floored MIN(component_available / component_qty).
     * A kit with no pool components yields no slots (nothing constrains it).
     *
     * @param  list<int>  $ancestry  the kit ids on the current recursion path (cycle guard)
     * @return list<AvailabilitySlotData>
     *
     * @throws RuntimeException on exceeding the configured nesting depth or on a cycle
     */
    private function computeSlots(int $kitProductId, int $storeId, Carbon $from, Carbon $to, int $depth, array $ancestry): array
    {
        $this->guardDepth($depth);

        // Eager-load the component products once (hot read path) so the per-slot
        // composition never issues an N+1 `Product::find()` per pool component.
        /** @var list<SerialisedComponent> $components */
        $components = SerialisedComponent::query()
            ->where('product_id', $kitProductId)
            ->where('binding', KitComponentBinding::Pool->value)
            ->with('componentProduct')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();

        // Per-slot accumulator: slot_start ISO => running MIN of floored ratios,
        // plus how many components reported a value for that slot. A slot only one
        // component reports means the others have NO availability data there (no
        // stock/snapshot) — the kit is unfulfillable, so it clamps to 0 below.
        /** @var array<string, int> $perSlot */
        $perSlot = [];
        /** @var array<string, int> $seenBy */
        $seenBy = [];
        $componentCount = 0;

        foreach ($components as $component) {
            $quantityPerKit = max(0.0, (float) $component->quantity);

            // A zero-quantity component places no constraint — skip it.
            if ($quantityPerKit <= 0.0) {
                continue;
            }

            $componentAvailability = $this->componentAvailabilityBySlot(
                $component->component_product_id,
                $component->componentProduct,
                $storeId,
                $from,
                $to,
                $depth,
                $ancestry,
            );

            $componentCount++;

            foreach ($componentAvailability as $slotKey => $available) {
                $ratio = (int) floor($available / $quantityPerKit);

                $perSlot[$slotKey] = array_key_exists($slotKey, $perSlot)
                    ? min($perSlot[$slotKey], $ratio)
                    : $ratio;
                $seenBy[$slotKey] = ($seenBy[$slotKey] ?? 0) + 1;
            }
        }

        // No constraining pool components → nothing to compose.
        if ($componentCount === 0) {
            return [];
        }

        $slots = [];

        foreach ($perSlot as $slotKey => $available) {
            // A slot not reported by EVERY component has a component with no data
            // there → unfulfillable, clamp to 0.
            $reported = ($seenBy[$slotKey] ?? 0) === $componentCount ? $available : 0;

            $slots[] = new AvailabilitySlotData(
                slot_start: $slotKey,
                total_stock: 0,
                total_demanded: 0,
                available: max(0, $reported),
                demand_breakdown: [],
                pending_checkin_quantity: 0,
            );
        }

        return $slots;
    }

    /**
     * Per-slot availability for a single component over the range, keyed by the
     * slot's ISO start.
     *
     * When the component is itself a kit, recurse (depth + 1). Otherwise read the
     * component's pre-calculated snapshot range — already reflecting ALL demands.
     *
     * The component {@see Product} is passed in pre-loaded (eager-loaded by
     * {@see computeSlots()}) so the hot read path never re-queries it per component.
     *
     * @param  list<int>  $ancestry
     * @return array<string, int>
     *
     * @throws RuntimeException on a cycle (component already on the recursion path)
     */
    private function componentAvailabilityBySlot(int $componentProductId, ?Product $component, int $storeId, Carbon $from, Carbon $to, int $depth, array $ancestry): array
    {
        if ($component !== null && $component->isKit()) {
            if (in_array($componentProductId, $ancestry, true)) {
                throw new RuntimeException(
                    "Kit composition cycle detected at product {$componentProductId}."
                );
            }

            $nestedSlots = $this->computeSlots(
                $componentProductId,
                $storeId,
                $from,
                $to,
                $depth + 1,
                [...$ancestry, $componentProductId],
            );

            $bySlot = [];

            foreach ($nestedSlots as $slot) {
                $bySlot[$slot->slot_start] = $slot->available;
            }

            return $bySlot;
        }

        $range = ($this->availabilityService)()->getAvailabilityRange($componentProductId, $storeId, $from, $to);

        $bySlot = [];

        foreach ($range->slots as $slot) {
            $bySlot[$slot->slot_start] = $slot->available;
        }

        return $bySlot;
    }

    /**
     * Reject a recursion deeper than the configured kit nesting bound. Enforced
     * at query time as a backstop to the composition-create guard.
     *
     * @throws RuntimeException
     */
    private function guardDepth(int $depth): void
    {
        $max = max(1, (int) config('availability.kit_nesting_max_depth', 3));

        if ($depth > $max) {
            throw new RuntimeException(
                "Kit nesting depth {$depth} exceeds the configured maximum of {$max}."
            );
        }
    }
}

<?php

namespace App\Services\Availability;

use App\Data\Availability\AvailabilityRangeData;
use App\Data\Availability\AvailabilitySlotData;
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
 * Only POOL components participate here. FIXED (container-bound) components are a
 * seam for M5-3b — they are skipped with a deliberate branch, never silently
 * folded into the pool calculation.
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
        $slots = $this->computeSlots($kitProductId, $storeId, $from, $to, 1, [$kitProductId]);

        return AvailabilityRangeData::make($kitProductId, $storeId, $from, $to, $slots, null);
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

        /** @var list<SerialisedComponent> $components */
        $components = SerialisedComponent::query()
            ->where('product_id', $kitProductId)
            ->where('binding', KitComponentBinding::Pool->value)
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
     * @param  list<int>  $ancestry
     * @return array<string, int>
     *
     * @throws RuntimeException on a cycle (component already on the recursion path)
     */
    private function componentAvailabilityBySlot(int $componentProductId, int $storeId, Carbon $from, Carbon $to, int $depth, array $ancestry): array
    {
        $component = Product::query()->find($componentProductId);

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

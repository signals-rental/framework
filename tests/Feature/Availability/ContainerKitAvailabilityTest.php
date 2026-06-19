<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\DemandPhase;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\Store;
use App\Services\Availability\KitAvailabilityCalculator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->calculator = app(KitAvailabilityCalculator::class);
});

/**
 * Create a housing snapshot row for the kit container product (its own serialised
 * availability per slot).
 */
function housingSnapshot(int $productId, int $storeId, Carbon $slot, int $available): AvailabilitySnapshot
{
    return AvailabilitySnapshot::factory()->create([
        'product_id' => $productId,
        'store_id' => $storeId,
        'slot_start' => $slot,
        'total_stock' => max(0, $available),
        'total_demanded' => 0,
        'available' => $available,
    ]);
}

/**
 * Create an active demand on a component for the window (a non-container booking
 * conflict by default).
 */
function componentDemand(int $productId, int $storeId, string $start, string $end, string $sourceType = 'opportunity_item'): Demand
{
    return Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse($start), Carbon::parse($end))
        ->create([
            'product_id' => $productId,
            'store_id' => $storeId,
            'quantity' => 1,
            'asset_id' => null,
            'source_type' => $sourceType,
        ]);
}

describe('serialised-permanent (kit-mode container)', function () {
    it('reports 1 when the kit container is free and its fixed item is present', function () {
        $kit = Product::factory()->containerable(ContainerAvailabilityMode::Kit)->create();
        $fixed = Product::factory()->serialised()->create();

        SerialisedComponent::factory()->fixed()->quantity(1)->create([
            'product_id' => $kit->id,
            'component_product_id' => $fixed->id,
        ]);

        $slot = Carbon::parse('2026-09-01T00:00:00Z');
        // The housing has 1 free kit container for the slot.
        housingSnapshot($kit->id, $this->store->id, $slot, 1);
        // The fixed component is held by its standing container demand (present).
        componentDemand($fixed->id, $this->store->id, '2026-09-01T00:00:00Z', '2199-01-01T00:00:00Z', 'container');

        $range = $this->calculator->calculate($kit->id, $this->store->id, $slot, $slot->copy()->addDay());

        expect($range->slots)->toHaveCount(1)
            ->and($range->slots[0]->available)->toBe(1);
    });

    it('reports 0 when the kit container is booked on an overlapping opportunity', function () {
        $kit = Product::factory()->containerable(ContainerAvailabilityMode::Kit)->create();

        $slot = Carbon::parse('2026-09-01T00:00:00Z');
        // The housing has NO free kit container for the slot (booked away).
        housingSnapshot($kit->id, $this->store->id, $slot, 0);

        $range = $this->calculator->calculate($kit->id, $this->store->id, $slot, $slot->copy()->addDay());

        expect($range->slots)->toHaveCount(1)
            ->and($range->slots[0]->available)->toBe(0);
    });

    it('clamps to 0 when a fixed component has a non-container booking conflict', function () {
        $kit = Product::factory()->containerable(ContainerAvailabilityMode::Kit)->create();
        $fixed = Product::factory()->serialised()->create();

        SerialisedComponent::factory()->fixed()->quantity(1)->create([
            'product_id' => $kit->id,
            'component_product_id' => $fixed->id,
        ]);

        $slot = Carbon::parse('2026-09-01T00:00:00Z');
        housingSnapshot($kit->id, $this->store->id, $slot, 1);
        // The fixed component is held in the kit (container demand) ...
        componentDemand($fixed->id, $this->store->id, '2026-09-01T00:00:00Z', '2199-01-01T00:00:00Z', 'container');
        // ... but ALSO individually booked over the window → genuine conflict.
        componentDemand($fixed->id, $this->store->id, '2026-09-01T00:00:00Z', '2026-09-03T00:00:00Z');

        $range = $this->calculator->calculate($kit->id, $this->store->id, $slot, $slot->copy()->addDay());

        expect($range->slots[0]->available)->toBe(0);
    });
});

describe('hybrid (fixed + pool)', function () {
    it('takes MIN of fixed (housing) and pool (component MIN)', function () {
        $kit = Product::factory()->containerable(ContainerAvailabilityMode::Hybrid)->create();
        $fixed = Product::factory()->serialised()->create();
        $pool = Product::factory()->bulk()->create();

        SerialisedComponent::factory()->fixed()->quantity(1)->create([
            'product_id' => $kit->id,
            'component_product_id' => $fixed->id,
        ]);
        SerialisedComponent::factory()->pool()->quantity(2)->create([
            'product_id' => $kit->id,
            'component_product_id' => $pool->id,
        ]);

        $slot = Carbon::parse('2026-09-01T00:00:00Z');
        // Housing: 5 free kit containers for the slot.
        housingSnapshot($kit->id, $this->store->id, $slot, 5);
        // Fixed component present (container-held), no conflict.
        componentDemand($fixed->id, $this->store->id, '2026-09-01T00:00:00Z', '2199-01-01T00:00:00Z', 'container');
        // Pool: 6 available, 2/kit → 3 kits. So MIN(5 fixed, 3 pool) = 3.
        AvailabilitySnapshot::factory()->create([
            'product_id' => $pool->id,
            'store_id' => $this->store->id,
            'slot_start' => $slot,
            'total_stock' => 6,
            'total_demanded' => 0,
            'available' => 6,
        ]);

        $range = $this->calculator->calculate($kit->id, $this->store->id, $slot, $slot->copy()->addDay());

        expect($range->slots)->toHaveCount(1)
            ->and($range->slots[0]->available)->toBe(3);
    });

    it('clamps a slot to 0 when a pool component has no coverage there', function () {
        // Hybrid kit with a pool component. The housing covers TWO slots but the pool
        // component only has a snapshot for the FIRST — the second slot is a pool
        // coverage gap and the kit is unfulfillable there (clamp 0), matching the
        // catalogue convention rather than defaulting to the housing value.
        $kit = Product::factory()->containerable(ContainerAvailabilityMode::Hybrid)->create();
        $pool = Product::factory()->bulk()->create();

        SerialisedComponent::factory()->pool()->quantity(1)->create([
            'product_id' => $kit->id,
            'component_product_id' => $pool->id,
        ]);

        $slot1 = Carbon::parse('2026-09-01T00:00:00Z');
        $slot2 = Carbon::parse('2026-09-02T00:00:00Z');

        // Housing free for BOTH slots.
        housingSnapshot($kit->id, $this->store->id, $slot1, 4);
        housingSnapshot($kit->id, $this->store->id, $slot2, 4);

        // Pool only covers slot 1 (4 available → 4 kits). Slot 2 has NO pool snapshot.
        AvailabilitySnapshot::factory()->create([
            'product_id' => $pool->id,
            'store_id' => $this->store->id,
            'slot_start' => $slot1,
            'total_stock' => 4,
            'total_demanded' => 0,
            'available' => 4,
        ]);

        $range = $this->calculator->calculate($kit->id, $this->store->id, $slot1, $slot2->copy()->addDay());

        // Two housing slots, ordered. Slot 1 is covered by the pool → MIN(4 housing,
        // 4 pool) = 4. Slot 2 is a pool coverage gap → clamped to 0 (not the housing
        // value of 4).
        $available = array_map(fn ($slot) => $slot->available, $range->slots);

        expect($range->slots)->toHaveCount(2)
            ->and($available[0])->toBe(4)
            ->and($available[1])->toBe(0);
    });

    it('keeps housing-only hybrid behaviour when there are no pool components', function () {
        // A hybrid kit with ONLY fixed components (no pool) must NOT be clamped to 0
        // on the missing-pool path — the housing value stands.
        $kit = Product::factory()->containerable(ContainerAvailabilityMode::Hybrid)->create();
        $fixed = Product::factory()->serialised()->create();

        SerialisedComponent::factory()->fixed()->quantity(1)->create([
            'product_id' => $kit->id,
            'component_product_id' => $fixed->id,
        ]);

        $slot = Carbon::parse('2026-09-01T00:00:00Z');
        housingSnapshot($kit->id, $this->store->id, $slot, 3);
        componentDemand($fixed->id, $this->store->id, '2026-09-01T00:00:00Z', '2199-01-01T00:00:00Z', 'container');

        $range = $this->calculator->calculate($kit->id, $this->store->id, $slot, $slot->copy()->addDay());

        expect($range->slots[0]->available)->toBe(3);
    });

    it('clamps to 0 when the pool side is exhausted', function () {
        $kit = Product::factory()->containerable(ContainerAvailabilityMode::Hybrid)->create();
        $pool = Product::factory()->bulk()->create();

        SerialisedComponent::factory()->pool()->quantity(2)->create([
            'product_id' => $kit->id,
            'component_product_id' => $pool->id,
        ]);

        $slot = Carbon::parse('2026-09-01T00:00:00Z');
        housingSnapshot($kit->id, $this->store->id, $slot, 5);
        // Pool: only 1 available, 2/kit → 0 kits.
        AvailabilitySnapshot::factory()->create([
            'product_id' => $pool->id,
            'store_id' => $this->store->id,
            'slot_start' => $slot,
            'total_stock' => 1,
            'total_demanded' => 0,
            'available' => 1,
        ]);

        $range = $this->calculator->calculate($kit->id, $this->store->id, $slot, $slot->copy()->addDay());

        expect($range->slots[0]->available)->toBe(0);
    });
});

it('keeps catalogue (pool) kit behaviour unchanged (regression)', function () {
    // No container mode → the M5-3a MIN composition over pool components only.
    $kit = Product::factory()->kit()->create();
    $a = Product::factory()->bulk()->create();
    $b = Product::factory()->bulk()->create();

    SerialisedComponent::factory()->pool()->quantity(2)->create(['product_id' => $kit->id, 'component_product_id' => $a->id]);
    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kit->id, 'component_product_id' => $b->id]);

    $slot = Carbon::parse('2026-09-01T00:00:00Z');
    AvailabilitySnapshot::factory()->create(['product_id' => $a->id, 'store_id' => $this->store->id, 'slot_start' => $slot, 'total_stock' => 10, 'total_demanded' => 0, 'available' => 10]);
    AvailabilitySnapshot::factory()->create(['product_id' => $b->id, 'store_id' => $this->store->id, 'slot_start' => $slot, 'total_stock' => 4, 'total_demanded' => 0, 'available' => 4]);

    // A: 10/2 = 5 ; B: 4/1 = 4 → MIN = 4.
    $range = $this->calculator->calculate($kit->id, $this->store->id, $slot, $slot->copy()->addDay());

    expect($range->slots[0]->available)->toBe(4);
});

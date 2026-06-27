<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Models\AvailabilitySnapshot;
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
 * Create a snapshot row for a component/store/slot with explicit availability.
 */
function componentSnapshot(int $productId, int $storeId, Carbon $slot, int $available): AvailabilitySnapshot
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

it('computes MIN(floor(component_available / qty)) across components', function () {
    $kit = Product::factory()->kit()->create();
    $a = Product::factory()->bulk()->create();
    $b = Product::factory()->bulk()->create();
    $c = Product::factory()->bulk()->create();

    // A: 10 available, 2/kit → 5 kits
    // B: 7 available, 1/kit → 7 kits
    // C: 9 available, 3/kit → 3 kits  ← MIN
    SerialisedComponent::factory()->pool()->quantity(2)->create(['product_id' => $kit->id, 'component_product_id' => $a->id]);
    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kit->id, 'component_product_id' => $b->id]);
    SerialisedComponent::factory()->pool()->quantity(3)->create(['product_id' => $kit->id, 'component_product_id' => $c->id]);

    $slot = Carbon::parse('2026-09-01T00:00:00Z');
    componentSnapshot($a->id, $this->store->id, $slot, 10);
    componentSnapshot($b->id, $this->store->id, $slot, 7);
    componentSnapshot($c->id, $this->store->id, $slot, 9);

    $range = $this->calculator->calculate($kit->id, $this->store->id, $slot, $slot->copy()->addDay());

    expect($range->slots)->toHaveCount(1)
        ->and($range->slots[0]->available)->toBe(3);
});

it('varies kit availability per slot over a range', function () {
    $kit = Product::factory()->kit()->create();
    $a = Product::factory()->bulk()->create();
    $b = Product::factory()->bulk()->create();

    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kit->id, 'component_product_id' => $a->id]);
    SerialisedComponent::factory()->pool()->quantity(2)->create(['product_id' => $kit->id, 'component_product_id' => $b->id]);

    $day1 = Carbon::parse('2026-09-01T00:00:00Z');
    $day2 = Carbon::parse('2026-09-02T00:00:00Z');

    // Day 1: A=5, B=8 → min(5, 4) = 4
    componentSnapshot($a->id, $this->store->id, $day1, 5);
    componentSnapshot($b->id, $this->store->id, $day1, 8);
    // Day 2: A=5, B=2 → min(5, 1) = 1
    componentSnapshot($a->id, $this->store->id, $day2, 5);
    componentSnapshot($b->id, $this->store->id, $day2, 2);

    $range = $this->calculator->calculate($kit->id, $this->store->id, $day1, $day2->copy()->addDay());

    $bySlot = collect($range->slots)->keyBy('slot_start')->map->available;

    expect($range->min_available)->toBe(1)
        ->and($range->max_available)->toBe(4)
        ->and($bySlot->values()->all())->toContain(4)
        ->and($bySlot->values()->all())->toContain(1);
});

it('clamps a slot to zero when a component has no availability data there', function () {
    $kit = Product::factory()->kit()->create();
    $a = Product::factory()->bulk()->create();
    $b = Product::factory()->bulk()->create();

    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kit->id, 'component_product_id' => $a->id]);
    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kit->id, 'component_product_id' => $b->id]);

    $slot = Carbon::parse('2026-09-01T00:00:00Z');
    // Only component A has a snapshot; B has none → kit unfulfillable.
    componentSnapshot($a->id, $this->store->id, $slot, 5);

    $range = $this->calculator->calculate($kit->id, $this->store->id, $slot, $slot->copy()->addDay());

    expect($range->slots)->toHaveCount(1)
        ->and($range->slots[0]->available)->toBe(0);
});

it('resolves a nested kit (kit of kits)', function () {
    // Outer kit → 1× inner kit ; inner kit → 1× leaf.
    $outer = Product::factory()->kit()->create();
    $inner = Product::factory()->kit()->create();
    $leaf = Product::factory()->bulk()->create();

    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $outer->id, 'component_product_id' => $inner->id]);
    SerialisedComponent::factory()->pool()->quantity(2)->create(['product_id' => $inner->id, 'component_product_id' => $leaf->id]);

    $slot = Carbon::parse('2026-09-01T00:00:00Z');
    // leaf has 9 → inner = floor(9/2) = 4 → outer = floor(4/1) = 4
    componentSnapshot($leaf->id, $this->store->id, $slot, 9);

    $range = $this->calculator->calculate($outer->id, $this->store->id, $slot, $slot->copy()->addDay());

    expect($range->slots)->toHaveCount(1)
        ->and($range->slots[0]->available)->toBe(4);
});

it('throws when kit nesting exceeds the configured max depth', function () {
    config(['availability.kit_nesting_max_depth' => 2]);

    // depth 1: k1 → k2 ; depth 2: k2 → k3 ; depth 3: k3 → leaf  → exceeds max 2.
    $k1 = Product::factory()->kit()->create();
    $k2 = Product::factory()->kit()->create();
    $k3 = Product::factory()->kit()->create();
    $leaf = Product::factory()->bulk()->create();

    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $k1->id, 'component_product_id' => $k2->id]);
    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $k2->id, 'component_product_id' => $k3->id]);
    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $k3->id, 'component_product_id' => $leaf->id]);

    $slot = Carbon::parse('2026-09-01T00:00:00Z');
    componentSnapshot($leaf->id, $this->store->id, $slot, 5);

    $this->calculator->calculate($k1->id, $this->store->id, $slot, $slot->copy()->addDay());
})->throws(RuntimeException::class);

it('detects a composition cycle', function () {
    config(['availability.kit_nesting_max_depth' => 5]);

    $k1 = Product::factory()->kit()->create();
    $k2 = Product::factory()->kit()->create();

    // k1 → k2 → k1 (cycle)
    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $k1->id, 'component_product_id' => $k2->id]);
    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $k2->id, 'component_product_id' => $k1->id]);

    $slot = Carbon::parse('2026-09-01T00:00:00Z');

    $this->calculator->calculate($k1->id, $this->store->id, $slot, $slot->copy()->addDay());
})->throws(RuntimeException::class);

it('ignores fixed-binding components (M5-3b seam)', function () {
    $kit = Product::factory()->kit()->create();
    $pool = Product::factory()->bulk()->create();
    $fixed = Product::factory()->bulk()->create();

    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kit->id, 'component_product_id' => $pool->id]);
    SerialisedComponent::factory()->fixed()->quantity(1)->create(['product_id' => $kit->id, 'component_product_id' => $fixed->id]);

    $slot = Carbon::parse('2026-09-01T00:00:00Z');
    componentSnapshot($pool->id, $this->store->id, $slot, 4);
    // Fixed component has NO snapshot, but must not constrain the catalogue-kit calc.

    $range = $this->calculator->calculate($kit->id, $this->store->id, $slot, $slot->copy()->addDay());

    expect($range->slots)->toHaveCount(1)
        ->and($range->slots[0]->available)->toBe(4);
});

it('skips a zero-quantity pool component so it places no constraint on the kit', function () {
    $kit = Product::factory()->kit()->create();
    $constraining = Product::factory()->bulk()->create();
    $zero = Product::factory()->bulk()->create();

    // A genuine constraint at 4 kits, plus a zero-quantity component that must be
    // skipped entirely (it would otherwise clamp the kit to its own availability).
    SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kit->id, 'component_product_id' => $constraining->id]);
    SerialisedComponent::factory()->pool()->quantity(0)->create(['product_id' => $kit->id, 'component_product_id' => $zero->id]);

    $slot = Carbon::parse('2026-09-01T00:00:00Z');
    componentSnapshot($constraining->id, $this->store->id, $slot, 4);
    // The zero-qty component has only 1 available; if it were NOT skipped the kit
    // would be clamped to 1 instead of 4.
    componentSnapshot($zero->id, $this->store->id, $slot, 1);

    $range = $this->calculator->calculate($kit->id, $this->store->id, $slot, $slot->copy()->addDay());

    expect($range->slots)->toHaveCount(1)
        ->and($range->slots[0]->available)->toBe(4);
});

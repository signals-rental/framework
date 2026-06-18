<?php

use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

it('casts attributes to their types', function () {
    $demand = Demand::factory()->create([
        'phase' => DemandPhase::Operational->value,
        'is_active' => true,
        'metadata' => ['opportunity_id' => 42],
    ]);

    $fresh = $demand->fresh();

    expect($fresh->phase)->toBe(DemandPhase::Operational)
        ->and($fresh->is_active)->toBeTrue()
        ->and($fresh->quantity)->toBeInt()
        ->and($fresh->priority)->toBeInt()
        ->and($fresh->starts_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($fresh->ends_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($fresh->metadata)->toBe(['opportunity_id' => 42]);
});

it('resolves its product, store, and asset relations', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    $asset = StockLevel::factory()->serialised()->create();

    $demand = Demand::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_id' => $asset->id,
    ]);

    expect($demand->product->is($product))->toBeTrue()
        ->and($demand->store->is($store))->toBeTrue()
        ->and($demand->asset->is($asset))->toBeTrue();
});

it('scopes to active demands only', function () {
    Demand::factory()->phase(DemandPhase::Committed)->create();
    Demand::factory()->phase(DemandPhase::Operational)->create();
    Demand::factory()->phase(DemandPhase::Draft)->create();
    Demand::factory()->phase(DemandPhase::Void)->create();

    expect(Demand::query()->active()->count())->toBe(2);
});

it('distinguishes definite and indefinite demands via the sentinel', function () {
    $definite = Demand::factory()->window(
        Carbon::parse('2026-07-01T09:00:00Z'),
        Carbon::parse('2026-07-05T17:00:00Z'),
    )->create();

    $indefinite = Demand::factory()->window(
        Carbon::parse('2026-07-01T09:00:00Z'),
        Demand::sentinel(),
    )->create();

    expect($definite->fresh()->is_indefinite)->toBeFalse()
        ->and($indefinite->fresh()->is_indefinite)->toBeTrue()
        ->and(Demand::query()->definite()->pluck('id')->all())->toBe([$definite->id])
        ->and(Demand::query()->indefinite()->pluck('id')->all())->toBe([$indefinite->id]);
});

it('finds overlapping demands by their window', function () {
    $inside = Demand::factory()->window(
        Carbon::parse('2026-07-10T00:00:00Z'),
        Carbon::parse('2026-07-12T00:00:00Z'),
    )->create();

    // Entirely before the query window — must not match.
    Demand::factory()->window(
        Carbon::parse('2026-07-01T00:00:00Z'),
        Carbon::parse('2026-07-05T00:00:00Z'),
    )->create();

    $matches = Demand::query()
        ->overlapping(
            Carbon::parse('2026-07-11T00:00:00Z'),
            Carbon::parse('2026-07-20T00:00:00Z'),
        )
        ->pluck('id')
        ->all();

    expect($matches)->toBe([$inside->id]);
});

it('treats adjacent windows as non-overlapping (half-open ranges)', function () {
    Demand::factory()->window(
        Carbon::parse('2026-07-01T00:00:00Z'),
        Carbon::parse('2026-07-05T00:00:00Z'),
    )->create();

    $matches = Demand::query()
        ->overlapping(
            Carbon::parse('2026-07-05T00:00:00Z'),
            Carbon::parse('2026-07-10T00:00:00Z'),
        )
        ->count();

    expect($matches)->toBe(0);
});

it('bakes product buffers into the buffered period', function () {
    [$start, $end] = Demand::bufferedPeriod(
        Carbon::parse('2026-07-01T09:00:00Z'),
        Carbon::parse('2026-07-01T17:00:00Z'),
        bufferBeforeMinutes: 120,
        bufferAfterMinutes: 240,
    );

    expect($start->toIso8601String())->toBe('2026-07-01T07:00:00+00:00')
        ->and($end->toIso8601String())->toBe('2026-07-01T21:00:00+00:00');
});

it('leaves the sentinel end untouched when baking buffers', function () {
    [$start, $end] = Demand::bufferedPeriod(
        Carbon::parse('2026-07-01T09:00:00Z'),
        Demand::sentinel(),
        bufferBeforeMinutes: 60,
        bufferAfterMinutes: 240,
    );

    expect($start->toIso8601String())->toBe('2026-07-01T08:00:00+00:00')
        ->and($end->equalTo(Demand::sentinel()))->toBeTrue();
});

it('clamps negative buffers to zero', function () {
    [$start, $end] = Demand::bufferedPeriod(
        Carbon::parse('2026-07-01T09:00:00Z'),
        Carbon::parse('2026-07-01T17:00:00Z'),
        bufferBeforeMinutes: -30,
        bufferAfterMinutes: -30,
    );

    expect($start->toIso8601String())->toBe('2026-07-01T09:00:00+00:00')
        ->and($end->toIso8601String())->toBe('2026-07-01T17:00:00+00:00');
});

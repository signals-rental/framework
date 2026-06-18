<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL availability lane — read model
|--------------------------------------------------------------------------
|
| Validates the availability read model against real Postgres: the authoritative
| tstzrange-based demand overlap, the advisory-locked recalculation pipeline, and
| that the point (on-the-fly from demands) and range (from snapshots) reads agree
| for the same scenario. Skips cleanly when Postgres is unreachable.
|
| Run the lane:
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

it('computes point availability from the tstzrange period column', function () {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    // The bulk demand's period is a real tstzrange; Demand::overlapping() uses
    // the native && operator on Postgres.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-08-01T00:00:00Z'), Carbon::parse('2026-08-04T00:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 4,
        ]);

    $point = app(AvailabilityService::class)
        ->getAvailability($product->id, $this->store->id, Carbon::parse('2026-08-02T12:00:00Z'));

    expect($point->total_stock)->toBe(10)
        ->and($point->total_demanded)->toBe(4)
        ->and($point->available)->toBe(6);
});

it('reconciles point (on-the-fly) and range (snapshot) reads for a scenario', function () {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 7,
    ]);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-08-10T00:00:00Z'), Carbon::parse('2026-08-12T00:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 2,
        ]);

    // The observer recalculated snapshots under the advisory lock on create.
    // Force a fresh recalculation across the window to be explicit.
    app(RecalculationPipeline::class)->recalculate(
        $product->id,
        $this->store->id,
        Carbon::parse('2026-08-10T00:00:00Z'),
        Carbon::parse('2026-08-12T00:00:00Z'),
    );

    $service = app(AvailabilityService::class);

    $range = $service->getAvailabilityRange(
        $product->id,
        $this->store->id,
        Carbon::parse('2026-08-10T00:00:00Z'),
        Carbon::parse('2026-08-12T00:00:00Z'),
    );

    expect($range->slots)->toHaveCount(2);

    // The snapshot for the first day must equal the live point computation.
    $point = $service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-08-10T06:00:00Z'));

    expect($range->slots[0]->available)->toBe($point->available)
        ->and($range->slots[0]->total_demanded)->toBe($point->total_demanded)
        ->and($range->min_available)->toBe(5);
});

it('recalculates serialised availability against the asset exclusion model', function () {
    $product = Product::factory()->serialised()->create();
    $assetA = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);
    StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    // One of the two serialised units is demanded for the window.
    Demand::factory()
        ->serialised()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-08-20T00:00:00Z'), Carbon::parse('2026-08-22T00:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'asset_id' => $assetA->id,
            'quantity' => 1,
        ]);

    $snapshot = AvailabilitySnapshot::query()
        ->forProductStore($product->id, $this->store->id)
        ->orderBy('slot_start')
        ->first();

    expect($snapshot->total_stock)->toBe(2)
        ->and($snapshot->total_demanded)->toBe(1)
        ->and($snapshot->available)->toBe(1);
});

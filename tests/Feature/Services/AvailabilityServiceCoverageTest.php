<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\AvailabilityService;
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
    $this->service = app(AvailabilityService::class);
});

it('routes composed kit products through the kit calculator on range reads', function () {
    // getAvailabilityRange (not the point read) must detect a composed product and
    // delegate to getKitAvailability rather than reading the (absent) kit snapshots.
    $kit = Product::factory()->kit()->create();
    $component = Product::factory()->bulk()->create();
    SerialisedComponent::factory()->pool()->quantity(1)->create([
        'product_id' => $kit->id,
        'component_product_id' => $component->id,
    ]);

    $slot = Carbon::parse('2026-09-01T00:00:00Z');
    AvailabilitySnapshot::factory()->create([
        'product_id' => $component->id,
        'store_id' => $this->store->id,
        'slot_start' => $slot,
        'available' => 5,
    ]);

    $range = $this->service->getAvailabilityRange(
        $kit->id,
        $this->store->id,
        $slot,
        $slot->copy()->addDay(),
    );

    expect($range->slots)->not->toBeEmpty()
        ->and($range->slots[0]->available)->toBe(5);
});

it('returns zero from availableForItem when the product does not exist', function () {
    $free = $this->service->availableForItem(
        999999,
        $this->store->id,
        Carbon::parse('2026-04-01T00:00:00Z'),
        Carbon::parse('2026-04-03T00:00:00Z'),
        'opportunity_item',
        1,
    );

    expect($free)->toBe(0);
});

it('returns an empty map from productTotalStock for an empty product id list', function () {
    expect($this->service->productTotalStock([], $this->store->id))->toBe([]);
});

it('filters gantt bars to the requested asset ids', function () {
    $product = Product::factory()->serialised()->create();
    $levels = StockLevel::factory()->serialised()->count(2)->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);
    $keepAsset = $levels->first();
    $dropAsset = $levels->last();

    $from = Carbon::parse('2026-08-10T00:00:00Z');
    $to = Carbon::parse('2026-08-14T00:00:00Z');

    // Two serialised demands, one per asset; only the first asset is requested.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-08-10T09:00:00Z'), Carbon::parse('2026-08-12T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'asset_id' => $keepAsset->id,
            'quantity' => 1,
            'source_type' => 'manual',
            'source_id' => 701,
            'metadata' => [],
        ]);
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-08-10T09:00:00Z'), Carbon::parse('2026-08-12T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'asset_id' => $dropAsset->id,
            'quantity' => 1,
            'source_type' => 'manual',
            'source_id' => 702,
            'metadata' => [],
        ]);

    $gantt = $this->service->getGantt(
        $product->id,
        $this->store->id,
        $from,
        $to,
        [$keepAsset->id],
    );

    // Only the requested asset's demand bar survives the whereIn filter.
    expect($gantt->demands)->toHaveCount(1)
        ->and($gantt->demands[0]->source_name)->toBe('manual #701');
});

it('names a gantt demand from the registry display name for a registered source type', function () {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 3,
    ]);

    $from = Carbon::parse('2026-08-10T00:00:00Z');
    $to = Carbon::parse('2026-08-14T00:00:00Z');

    // `container` is a registered demand source type → its registry displayName is
    // used; `manual` is NOT registered → the raw "source_type #id" fallback.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-08-10T09:00:00Z'), Carbon::parse('2026-08-12T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
            'source_type' => 'container',
            'source_id' => 808,
            'metadata' => [],
        ]);
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-08-10T09:00:00Z'), Carbon::parse('2026-08-12T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
            'source_type' => 'manual',
            'source_id' => 909,
            'metadata' => [],
        ]);

    $gantt = $this->service->getGantt($product->id, $this->store->id, $from, $to);

    $names = collect($gantt->demands)->pluck('source_name')->all();

    // Registered source uses the registry display name (not the raw "container #..").
    expect($names)->toContain('manual #909')
        ->and(collect($names)->first(fn (string $n): bool => str_ends_with($n, '#808')))
        ->not->toBe('container #808');
});

it('collapses a fully out-of-horizon window to a single slot in availableForItem', function () {
    // A window entirely in the distant past clamps to from >= to; clampToHorizon
    // then preserves the original `from` so a slot is still evaluated. With no
    // competing demand the worst-slot availability equals total stock.
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 4,
    ]);

    $free = $this->service->availableForItem(
        $product->id,
        $this->store->id,
        Carbon::now('UTC')->subYears(5),
        Carbon::now('UTC')->subYears(3),
        'opportunity_item',
        12345,
    );

    expect($free)->toBe(4);
});

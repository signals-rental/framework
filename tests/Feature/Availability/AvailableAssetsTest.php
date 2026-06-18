<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Product;
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

/**
 * Create a serialised, asset-pinned active demand for the given asset/window.
 */
function demandOnAsset(int $productId, int $storeId, int $assetId, string $start, string $end, DemandPhase $phase = DemandPhase::Committed): Demand
{
    return Demand::factory()
        ->serialised()
        ->phase($phase)
        ->window(Carbon::parse($start), Carbon::parse($end))
        ->create([
            'product_id' => $productId,
            'store_id' => $storeId,
            'asset_id' => $assetId,
            'quantity' => 1,
        ]);
}

describe('getAvailableAssets', function () {
    it('returns serialised assets with no overlapping active demand', function () {
        $product = Product::factory()->serialised()->create();
        $free = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);
        $busy = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        // The "busy" asset is demanded across the requested window.
        demandOnAsset($product->id, $this->store->id, $busy->id, '2026-07-01T09:00:00Z', '2026-07-05T17:00:00Z');

        $assets = $this->service->getAvailableAssets(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-07-02T00:00:00Z'),
            Carbon::parse('2026-07-04T00:00:00Z'),
        );

        expect($assets->pluck('id')->all())->toBe([$free->id]);
    });

    it('includes an asset whose demand does not overlap the window', function () {
        $product = Product::factory()->serialised()->create();
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        // Demand ends before the requested window starts → no overlap.
        demandOnAsset($product->id, $this->store->id, $asset->id, '2026-07-01T00:00:00Z', '2026-07-03T00:00:00Z');

        $assets = $this->service->getAvailableAssets(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-07-10T00:00:00Z'),
            Carbon::parse('2026-07-12T00:00:00Z'),
        );

        expect($assets->pluck('id')->all())->toBe([$asset->id]);
    });

    it('ignores inactive demands when deciding availability', function () {
        $product = Product::factory()->serialised()->create();
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        // A Void (inactive) demand overlapping the window must NOT exclude the asset.
        demandOnAsset($product->id, $this->store->id, $asset->id, '2026-07-01T00:00:00Z', '2026-07-10T00:00:00Z', DemandPhase::Void);

        $assets = $this->service->getAvailableAssets(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-07-02T00:00:00Z'),
            Carbon::parse('2026-07-04T00:00:00Z'),
        );

        expect($assets->pluck('id')->all())->toBe([$asset->id]);
    });

    it('excludes assets from other stores and other products', function () {
        $product = Product::factory()->serialised()->create();
        $otherStore = Store::factory()->create(['timezone' => 'UTC']);
        $otherProduct = Product::factory()->serialised()->create();

        $mine = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);
        StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $otherStore->id,
        ]);
        StockLevel::factory()->serialised()->create([
            'product_id' => $otherProduct->id,
            'store_id' => $this->store->id,
        ]);

        $assets = $this->service->getAvailableAssets(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-07-02T00:00:00Z'),
            Carbon::parse('2026-07-04T00:00:00Z'),
        );

        expect($assets->pluck('id')->all())->toBe([$mine->id]);
    });

    it('returns an empty collection for a bulk product (no discrete assets)', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 20,
        ]);

        $assets = $this->service->getAvailableAssets(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-07-02T00:00:00Z'),
            Carbon::parse('2026-07-04T00:00:00Z'),
        );

        expect($assets)->toBeEmpty();
    });
});

describe('checkAssetAvailable', function () {
    it('is false when an active demand overlaps the window', function () {
        $product = Product::factory()->serialised()->create();
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        demandOnAsset($product->id, $this->store->id, $asset->id, '2026-07-01T00:00:00Z', '2026-07-05T00:00:00Z');

        expect($this->service->checkAssetAvailable(
            $asset->id,
            Carbon::parse('2026-07-03T00:00:00Z'),
            Carbon::parse('2026-07-04T00:00:00Z'),
        ))->toBeFalse();
    });

    it('is true when no active demand overlaps the window', function () {
        $product = Product::factory()->serialised()->create();
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        demandOnAsset($product->id, $this->store->id, $asset->id, '2026-07-01T00:00:00Z', '2026-07-05T00:00:00Z');

        expect($this->service->checkAssetAvailable(
            $asset->id,
            Carbon::parse('2026-07-10T00:00:00Z'),
            Carbon::parse('2026-07-12T00:00:00Z'),
        ))->toBeTrue();
    });

    it('is false for a stock level that does not exist', function () {
        expect($this->service->checkAssetAvailable(
            999_999,
            Carbon::parse('2026-07-10T00:00:00Z'),
            Carbon::parse('2026-07-12T00:00:00Z'),
        ))->toBeFalse();
    });
});

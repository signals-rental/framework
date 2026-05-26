<?php

use App\Enums\RateTransactionType;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\Store;
use App\Services\RateEngine\RateResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

function resolver(): RateResolver
{
    return app(RateResolver::class);
}

it('returns the highest-priority matching rate', function () {
    $product = Product::factory()->create();
    ProductRate::factory()->for($product)->withPriority(1)->create(['price' => 1000]);
    $winner = ProductRate::factory()->for($product)->withPriority(10)->create(['price' => 5000]);

    $resolved = resolver()->resolve($product, RateTransactionType::Rental);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($winner->id)
        ->and($resolved->price)->toBe(5000);
});

it('eager loads the rate definition', function () {
    $product = Product::factory()->create();
    ProductRate::factory()->for($product)->create();

    $resolved = resolver()->resolve($product, RateTransactionType::Rental);

    expect($resolved->relationLoaded('rateDefinition'))->toBeTrue()
        ->and($resolved->rateDefinition)->toBeInstanceOf(RateDefinition::class);
});

it('returns null when no rate matches (caller falls back to default pricing)', function () {
    $product = Product::factory()->create();

    expect(resolver()->resolve($product, RateTransactionType::Rental))->toBeNull();
});

it('filters by transaction type', function () {
    $product = Product::factory()->create();
    ProductRate::factory()->for($product)->forSale()->create();

    expect(resolver()->resolve($product, RateTransactionType::Rental))->toBeNull()
        ->and(resolver()->resolve($product, RateTransactionType::Sale))->not->toBeNull();
});

it('prefers a store-specific rate over an all-stores rate at equal priority', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    ProductRate::factory()->for($product)->create(['store_id' => null, 'price' => 1000]);
    $storeRate = ProductRate::factory()->for($product)->for($store)->create(['price' => 2000]);

    $resolved = resolver()->resolve($product, RateTransactionType::Rental, $store->id);

    expect($resolved->id)->toBe($storeRate->id);
});

it('falls back to an all-stores rate when no store-specific rate exists', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    $allStores = ProductRate::factory()->for($product)->create(['store_id' => null]);

    $resolved = resolver()->resolve($product, RateTransactionType::Rental, $store->id);

    expect($resolved->id)->toBe($allStores->id);
});

it('only matches rates valid on the requested date', function () {
    $product = Product::factory()->create();
    ProductRate::factory()->for($product)->create([
        'valid_from' => '2026-06-01',
        'valid_to' => '2026-06-30',
    ]);

    expect(resolver()->resolve($product, RateTransactionType::Rental, null, Carbon::parse('2026-07-15')))->toBeNull()
        ->and(resolver()->resolve($product, RateTransactionType::Rental, null, Carbon::parse('2026-06-15')))->not->toBeNull();
});

it('matches open-ended validity windows', function () {
    $product = Product::factory()->create();
    ProductRate::factory()->for($product)->create(['valid_from' => null, 'valid_to' => null]);

    expect(resolver()->resolve($product, RateTransactionType::Rental, null, Carbon::parse('2030-01-01')))->not->toBeNull();
});

it('resolves directly without caching when the driver has no tag support', function () {
    Cache::partialMock()->shouldReceive('supportsTags')->andReturnFalse();

    $product = Product::factory()->create();
    $rate = ProductRate::factory()->for($product)->create();

    expect(resolver()->resolve($product, RateTransactionType::Rental)?->id)->toBe($rate->id);
});

it('caches the resolution and serves it without re-querying', function () {
    $product = Product::factory()->create();
    $rate = ProductRate::factory()->for($product)->create();

    // Prime the cache.
    expect(resolver()->resolve($product, RateTransactionType::Rental)->id)->toBe($rate->id);

    // Delete via the query builder, which does NOT fire model events (so the
    // cache is not invalidated). A cache hit still returns the stale row.
    ProductRate::query()->whereKey($rate->id)->delete();

    expect(resolver()->resolve($product, RateTransactionType::Rental)?->id)->toBe($rate->id);
});

it('invalidates the cache when a product rate is written', function () {
    $product = Product::factory()->create();
    ProductRate::factory()->for($product)->withPriority(1)->create(['price' => 1000]);

    expect(resolver()->resolve($product, RateTransactionType::Rental)->price)->toBe(1000);

    // A new higher-priority rate (through the model, firing events) busts the cache.
    ProductRate::factory()->for($product)->withPriority(10)->create(['price' => 9000]);

    expect(resolver()->resolve($product, RateTransactionType::Rental)->price)->toBe(9000);
});

it('invalidates the resolution cache when a rate definition is written', function () {
    $product = Product::factory()->create();
    $rate = ProductRate::factory()->for($product)->create();

    expect(resolver()->resolve($product, RateTransactionType::Rental)->id)->toBe($rate->id);

    // Editing any rate definition busts cached resolutions (which carry a
    // loaded definition), so stale config is never served.
    $rate->rateDefinition->update(['name' => 'Edited']);

    // Remove the row without events; if the cache had not been busted by the
    // definition write, the stale row would still be returned.
    ProductRate::query()->whereKey($rate->id)->delete();

    expect(resolver()->resolve($product, RateTransactionType::Rental))->toBeNull();
});

<?php

use App\Data\Opportunities\ProductSearchResultData;
use App\Models\Accessory;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Opportunities\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(ProductSearchService::class);
});

it('returns a collection of result DTOs for a matching query', function (): void {
    Product::factory()->create(['name' => 'Spiider Light', 'sku' => 'SPD-001']);
    Product::factory()->create(['name' => 'Unrelated Cable', 'sku' => 'CAB-009']);

    $results = $this->service->search('spiider');

    expect($results)->toBeInstanceOf(Collection::class)
        ->and($results)->toHaveCount(1)
        ->and($results->first())->toBeInstanceOf(ProductSearchResultData::class)
        ->and($results->first()->name)->toBe('Spiider Light')
        ->and($results->first()->sku)->toBe('SPD-001');
});

it('matches on sku as well as name', function (): void {
    Product::factory()->create(['name' => 'Moving Head', 'sku' => 'MH-ROBE-600']);

    $results = $this->service->search('robe');

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Moving Head');
});

it('returns an empty collection for a blank query', function (): void {
    Product::factory()->create(['name' => 'Spiider Light']);

    expect($this->service->search('')->isEmpty())->toBeTrue()
        ->and($this->service->search('   ')->isEmpty())->toBeTrue();
});

it('excludes inactive products', function (): void {
    Product::factory()->create(['name' => 'Active Spiider', 'is_active' => true]);
    Product::factory()->inactive()->create(['name' => 'Retired Spiider']);

    $results = $this->service->search('spiider');

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Active Spiider');
});

it('ranks an exact name match ahead of a substring match (sqlite like path)', function (): void {
    Product::factory()->create(['name' => 'Pro Light Mover', 'sku' => 'A']);
    Product::factory()->create(['name' => 'Light', 'sku' => 'B']);

    $results = $this->service->search('Light');

    // Exact "Light" should outrank "Pro Light Mover".
    expect($results->first()->name)->toBe('Light');
});

it('honours the limit argument', function (): void {
    Product::factory()->count(5)->create(['name' => 'Spiider Variant '.fake()->unique()->word()]);

    $results = $this->service->search('spiider', null, 3);

    expect($results)->toHaveCount(3);
});

it('resolves the product default rate via the rate engine', function (): void {
    $product = Product::factory()->create(['name' => 'Rated Spiider']);

    $definition = RateDefinition::factory()->create(); // Period / Daily
    ProductRate::factory()->create([
        'product_id' => $product->id,
        'rate_definition_id' => $definition->id,
        'price' => 12500,
        'store_id' => null,
    ]);

    $result = $this->service->search('rated spiider')->first();

    expect($result->default_rate)->not->toBeNull()
        ->and($result->default_rate)->toBeString();
});

it('returns a null default rate when no rate is assigned', function (): void {
    Product::factory()->create(['name' => 'Unpriced Spiider']);

    $result = $this->service->search('unpriced spiider')->first();

    expect($result->default_rate)->toBeNull();
});

it('includes linked accessories with name, sku and ratio', function (): void {
    $primary = Product::factory()->create(['name' => 'Spiider Primary']);
    $accessory = Product::factory()->create(['name' => 'Safety Clamp', 'sku' => 'CLMP-1']);

    Accessory::factory()->create([
        'product_id' => $primary->id,
        'accessory_product_id' => $accessory->id,
        'quantity' => 2,
        'included' => true,
        'zero_priced' => true,
    ]);

    $result = $this->service->search('spiider primary')->first();

    expect($result->accessories)->toHaveCount(1)
        ->and($result->accessories[0]->name)->toBe('Safety Clamp')
        ->and($result->accessories[0]->sku)->toBe('CLMP-1')
        ->and($result->accessories[0]->ratio)->toBe('2.00')
        ->and($result->accessories[0]->included)->toBeTrue();
});

it('omits availability when no store is supplied', function (): void {
    Product::factory()->create(['name' => 'Storeless Spiider']);

    $result = $this->service->search('storeless spiider')->first();

    expect($result->availability)->toBeNull();
});

it('resolves a point availability status when a store is supplied', function (): void {
    $store = Store::factory()->create();
    Product::factory()->create(['name' => 'Storeful Spiider', 'track_availability' => true]);

    $result = $this->service->search('storeful spiider', $store->id)->first();

    // No stock seeded → "out"; the status is one of the three editor chips.
    expect($result->availability)->toBeIn(['available', 'reserved', 'out']);
});

it('skips a linked accessory whose product has been deleted', function (): void {
    $primary = Product::factory()->create(['name' => 'Spiider Deleted Acc']);
    $accessory = Product::factory()->create(['name' => 'Vanished Clamp']);

    Accessory::factory()->create([
        'product_id' => $primary->id,
        'accessory_product_id' => $accessory->id,
    ]);

    // Soft-delete the linked product so accessoryProduct resolves to null and the
    // accessory is dropped from the result (null-return branch).
    $accessory->delete();

    $result = $this->service->search('spiider deleted acc')->first();

    expect($result->accessories)->toBe([]);
});

it('reports an available status when free stock exists at the store', function (): void {
    $store = Store::factory()->create();
    $product = Product::factory()->bulk()->create(['name' => 'Stocked Spiider', 'track_availability' => true]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);

    $result = $this->service->search('stocked spiider', $store->id)->first();

    expect($result->availability)->toBe('available');
});

it('builds a lightweight catalogue index payload for the client tier', function (): void {
    Product::factory()->create(['name' => 'Indexed Spiider', 'sku' => 'IDX-1']);
    Product::factory()->inactive()->create(['name' => 'Hidden Spiider']);

    $catalogue = $this->service->catalogueIndex();

    expect($catalogue)->toBeArray()
        ->and($catalogue)->toHaveCount(1)
        ->and($catalogue[0])->toHaveKeys(['id', 'name', 'sku', 'default_rate', 'accessories', 'image_url', 'initials'])
        ->and($catalogue[0]['name'])->toBe('Indexed Spiider')
        // Availability is omitted from the client index (store/date specific).
        ->and($catalogue[0]['availability'])->toBeNull()
        ->and($catalogue[0]['initials'])->toBe('IS');
});

it('includes a signed image url and initials fallback for picker rows', function (): void {
    Product::factory()->create([
        'name' => 'Gallery Light',
        'icon_thumb_url' => 'icons/products/1/thumbs/icon.jpg',
    ]);
    Product::factory()->create(['name' => 'Plain Cable']);

    $withImage = $this->service->search('gallery')->first();
    $withoutImage = $this->service->search('plain')->first();

    expect($withImage->image_url)->not->toBeNull()
        ->and($withImage->initials)->toBe('GL')
        ->and($withoutImage->image_url)->toBeNull()
        ->and($withoutImage->initials)->toBe('PC');
});

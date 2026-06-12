<?php

use App\Actions\Products\CreateStockLevel;
use App\Data\Products\CreateStockLevelData;
use App\Data\Products\StockLevelData;
use App\Enums\StockCategory;
use App\Events\AuditableEvent;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a stock level', function () {
    Event::fake([AuditableEvent::class]);

    $product = Product::factory()->create();
    $store = Store::factory()->create();

    $data = CreateStockLevelData::from([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 10,
    ]);

    $result = (new CreateStockLevel)($data);

    expect($result)->toBeInstanceOf(StockLevelData::class)
        ->and($result->product_id)->toBe($product->id)
        ->and($result->store_id)->toBe($store->id);

    $this->assertDatabaseHas('stock_levels', [
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    Event::assertDispatched(AuditableEvent::class);
});

it('derives serialised stock category from a serialised product', function () {
    $product = Product::factory()->serialised()->create();
    $store = Store::factory()->create();

    $result = (new CreateStockLevel)(CreateStockLevelData::from([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'serial_number' => 'SN-001',
        'asset_number' => 'A-001',
        'quantity_held' => 1,
    ]));

    expect($result->stock_category)->toBe(StockCategory::SerialisedStock->value);

    $stockLevel = StockLevel::findOrFail($result->id);
    expect($stockLevel->stock_category)->toBe(StockCategory::SerialisedStock);
});

it('derives bulk stock category from a bulk product even when caller passes serialised', function () {
    $product = Product::factory()->bulk()->create();
    $store = Store::factory()->create();

    $result = (new CreateStockLevel)(CreateStockLevelData::from([
        'product_id' => $product->id,
        'store_id' => $store->id,
        // Caller-supplied category must be ignored in favour of the product's method.
        'stock_category' => StockCategory::SerialisedStock->value,
        'quantity_held' => 5,
    ]));

    expect($result->stock_category)->toBe(StockCategory::BulkStock->value);
});

it('places a serialised product stock level into the serialised scope', function () {
    $product = Product::factory()->serialised()->create();
    $store = Store::factory()->create();

    $result = (new CreateStockLevel)(CreateStockLevelData::from([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'serial_number' => 'SN-100',
        'asset_number' => 'A-100',
        'quantity_held' => 1,
    ]));

    expect(StockLevel::serialized()->whereKey($result->id)->exists())->toBeTrue()
        ->and(StockLevel::bulk()->whereKey($result->id)->exists())->toBeFalse();
});

it('requires stock.adjust permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();
    $store = Store::factory()->create();

    $data = CreateStockLevelData::from([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    (new CreateStockLevel)($data);
})->throws(AuthorizationException::class);

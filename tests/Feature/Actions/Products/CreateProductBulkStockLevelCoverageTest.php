<?php

use App\Actions\Products\CreateProduct;
use App\Data\Products\CreateProductData;
use App\Enums\ProductType;
use App\Enums\StockCategory;
use App\Enums\StockMethod;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('auto-creates a zero-quantity stock level for a bulk product at the default store', function () {
    // A bulk product needs a single stock level ready to receive transactions. With
    // a default store present, CreateProduct provisions it up front at zero qty.
    $store = Store::factory()->create(['is_default' => true]);

    $result = (new CreateProduct)(CreateProductData::from([
        'name' => 'Bulk Cable Reel',
        'product_type' => ProductType::Rental->value,
        'stock_method' => StockMethod::Bulk->value,
    ]));

    $stockLevel = StockLevel::query()
        ->where('product_id', $result->id)
        ->where('store_id', $store->id)
        ->first();

    expect($stockLevel)->not->toBeNull()
        ->and($stockLevel->stock_category)->toBe(StockCategory::BulkStock)
        ->and((float) $stockLevel->quantity_held)->toBe(0.0)
        ->and((float) $stockLevel->quantity_allocated)->toBe(0.0)
        ->and((float) $stockLevel->quantity_unavailable)->toBe(0.0)
        ->and((float) $stockLevel->quantity_on_order)->toBe(0.0);
});

it('falls back to the first store when none is marked default', function () {
    // No default store, but a store exists — the auto stock level lands on it.
    $store = Store::factory()->create(['is_default' => false]);

    $result = (new CreateProduct)(CreateProductData::from([
        'name' => 'Bulk Sandbag',
        'product_type' => ProductType::Rental->value,
        'stock_method' => StockMethod::Bulk->value,
    ]));

    expect(StockLevel::query()->where('product_id', $result->id)->where('store_id', $store->id)->exists())->toBeTrue();
});

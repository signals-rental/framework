<?php

use App\Data\Products\StockLevelData;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;

it('maps product_id to item_id key in output', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    $stockLevel->refresh();

    $data = StockLevelData::fromModel($stockLevel);
    $output = $data->toArray();

    expect($output)->toHaveKey('item_id', $product->id);
    expect($output)->not->toHaveKey('product_id');
});

it('formats quantity fields as decimal strings', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 10,
    ]);
    $stockLevel->refresh();

    $data = StockLevelData::fromModel($stockLevel);

    expect($data->quantity_held)->toBe('10.0');
    expect($data->quantity_allocated)->toBe('0.0');
    expect($data->quantity_unavailable)->toBe('0.0');
    expect($data->quantity_on_order)->toBe('0.0');
});

it('derives stock_type_name correctly', function (int $type, string $expectedName) {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'stock_type' => $type,
    ]);
    $stockLevel->refresh();

    $data = StockLevelData::fromModel($stockLevel);

    expect($data->stock_type)->toBe($type);
    expect($data->stock_type_name)->toBe($expectedName);
})->with([
    [1, 'Rental'],
    [2, 'Sale'],
]);

it('derives stock_category_name correctly', function (int $category, string $expectedName) {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'stock_category' => $category,
    ]);
    $stockLevel->refresh();

    $data = StockLevelData::fromModel($stockLevel);

    expect($data->stock_category)->toBe($category);
    expect($data->stock_category_name)->toBe($expectedName);
})->with([
    [10, 'Bulk Stock'],
    [50, 'Serialised Stock'],
]);

it('includes item when product relation is loaded', function () {
    $product = Product::factory()->create(['name' => 'Test Speaker']);
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    $stockLevel->load('product');

    $data = StockLevelData::fromModel($stockLevel);

    expect($data->item)->not->toBeNull()
        ->and($data->item->id)->toBe($product->id)
        ->and($data->item->name)->toBe('Test Speaker');
});

it('excludes item when product relation is not loaded', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    $data = StockLevelData::fromModel($stockLevel);

    expect($data->item)->toBeNull();
});

it('includes store_name when store relation is loaded', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create(['name' => 'Main Warehouse']);
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    $stockLevel->load('store');

    $data = StockLevelData::fromModel($stockLevel);

    expect($data->store_name)->toBe('Main Warehouse');
});

it('formats timestamps in CRMS ISO 8601 format', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    $stockLevel->refresh();

    $data = StockLevelData::fromModel($stockLevel);

    expect($data->created_at)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    expect($data->updated_at)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
});

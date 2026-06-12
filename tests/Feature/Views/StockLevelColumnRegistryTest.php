<?php

use App\Models\StockLevel;
use App\Views\StockLevelColumnRegistry;

it('returns stock_levels as entity type', function () {
    $registry = new StockLevelColumnRegistry;
    expect($registry->entityType())->toBe('stock_levels');
});

it('returns StockLevel model class', function () {
    $registry = new StockLevelColumnRegistry;
    expect($registry->modelClass())->toBe(StockLevel::class);
});

it('defines default columns', function () {
    $registry = new StockLevelColumnRegistry;
    $defaults = $registry->defaultColumns();
    expect($defaults)->toContain('item_name', 'store', 'quantity_held');
});

it('defines all expected columns', function () {
    $registry = new StockLevelColumnRegistry;
    $columns = $registry->allColumns();
    $keys = array_keys($columns);
    expect($keys)->toContain('item_name', 'product', 'store', 'quantity_held', 'created_at');
});

it('keeps default columns consistent with defined columns', function () {
    $registry = new StockLevelColumnRegistry;
    $columnKeys = array_keys($registry->allColumns());

    foreach ($registry->defaultColumns() as $key) {
        expect($columnKeys)->toContain($key);
    }
});

it('exposes stock_method as a non-sortable, non-filterable display column', function () {
    $registry = new StockLevelColumnRegistry;
    $column = $registry->get('stock_method');

    expect($column)->not->toBeNull()
        ->and($column->sortable)->toBeFalse()
        ->and($column->filterable)->toBeFalse();
});

it('exposes the expected filterable fields', function () {
    $registry = new StockLevelColumnRegistry;

    $filterable = array_values(array_map(
        fn ($col) => $col->key,
        array_filter($registry->allColumns(), fn ($col): bool => $col->filterable),
    ));

    expect($filterable)->toContain(
        'item_name', 'asset_number', 'serial_number', 'barcode',
        'store', 'product', 'stock_type', 'stock_category', 'location',
    );
});

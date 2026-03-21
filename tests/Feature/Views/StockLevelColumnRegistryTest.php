<?php

use App\Views\StockLevelColumnRegistry;

it('returns stock_levels as entity type', function () {
    $registry = new StockLevelColumnRegistry;
    expect($registry->entityType())->toBe('stock_levels');
});

it('returns StockLevel model class', function () {
    $registry = new StockLevelColumnRegistry;
    expect($registry->modelClass())->toBe(\App\Models\StockLevel::class);
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

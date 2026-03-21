<?php

use App\Views\Column;
use App\Views\ProductColumnRegistry;
use App\Views\StockLevelColumnRegistry;

describe('ProductColumnRegistry', function () {
    it('returns products as entity type', function () {
        $registry = new ProductColumnRegistry;
        expect($registry->entityType())->toBe('products');
    });

    it('returns all product columns', function () {
        $registry = new ProductColumnRegistry;
        $columns = $registry->allColumns();

        expect($columns)->toHaveKey('name')
            ->and($columns)->toHaveKey('product_type')
            ->and($columns)->toHaveKey('sku')
            ->and($columns)->toHaveKey('is_active')
            ->and($columns)->toHaveKey('created_at')
            ->and($columns['name'])->toBeInstanceOf(Column::class);
    });

    it('returns default columns for products', function () {
        $registry = new ProductColumnRegistry;
        $defaults = $registry->defaultColumns();

        expect($defaults)->toContain('name', 'product_type', 'product_group', 'sku', 'is_active', 'created_at');
    });

    it('returns default columns as subset of all columns', function () {
        $registry = new ProductColumnRegistry;
        $allKeys = array_keys($registry->allColumns());
        $defaults = $registry->defaultColumns();

        foreach ($defaults as $default) {
            expect($allKeys)->toContain($default);
        }
    });

    it('validates column keys', function () {
        $registry = new ProductColumnRegistry;
        $invalid = $registry->validateColumns(['name', 'nonexistent']);
        expect($invalid)->toBe(['nonexistent']);
    });

    it('gets a specific column', function () {
        $registry = new ProductColumnRegistry;
        $column = $registry->get('name');

        expect($column)->not->toBeNull()
            ->and($column)->toBeInstanceOf(Column::class)
            ->and($column->label)->toBe('Name')
            ->and($column->sortable)->toBeTrue();
    });

    it('returns correct column types', function () {
        $registry = new ProductColumnRegistry;

        expect($registry->get('product_type')->type)->toBe('enum')
            ->and($registry->get('is_active')->type)->toBe('boolean')
            ->and($registry->get('created_at')->type)->toBe('datetime')
            ->and($registry->get('replacement_charge')->type)->toBe('money');
    });

    it('returns null for unknown column', function () {
        $registry = new ProductColumnRegistry;
        expect($registry->get('nonexistent'))->toBeNull();
    });
});

describe('StockLevelColumnRegistry', function () {
    it('returns stock_levels as entity type', function () {
        $registry = new StockLevelColumnRegistry;
        expect($registry->entityType())->toBe('stock_levels');
    });

    it('returns all stock level columns', function () {
        $registry = new StockLevelColumnRegistry;
        $columns = $registry->allColumns();

        expect($columns)->toHaveKey('item_name')
            ->and($columns)->toHaveKey('asset_number')
            ->and($columns)->toHaveKey('serial_number')
            ->and($columns)->toHaveKey('quantity_held')
            ->and($columns)->toHaveKey('created_at')
            ->and($columns['item_name'])->toBeInstanceOf(Column::class);
    });

    it('returns default columns for stock levels', function () {
        $registry = new StockLevelColumnRegistry;
        $defaults = $registry->defaultColumns();

        expect($defaults)->toContain('item_name', 'asset_number', 'serial_number', 'store', 'quantity_held', 'quantity_allocated', 'created_at');
    });

    it('returns default columns as subset of all columns', function () {
        $registry = new StockLevelColumnRegistry;
        $allKeys = array_keys($registry->allColumns());
        $defaults = $registry->defaultColumns();

        foreach ($defaults as $default) {
            expect($allKeys)->toContain($default);
        }
    });

    it('validates column keys', function () {
        $registry = new StockLevelColumnRegistry;
        $invalid = $registry->validateColumns(['item_name', 'fake_column']);
        expect($invalid)->toBe(['fake_column']);
    });

    it('returns correct column types', function () {
        $registry = new StockLevelColumnRegistry;

        expect($registry->get('stock_type')->type)->toBe('enum')
            ->and($registry->get('stock_category')->type)->toBe('enum')
            ->and($registry->get('created_at')->type)->toBe('datetime');
    });
});

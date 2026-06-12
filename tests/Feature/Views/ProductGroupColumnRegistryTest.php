<?php

use App\Models\ProductGroup;
use App\Views\Column;
use App\Views\ProductGroupColumnRegistry;

describe('ProductGroupColumnRegistry', function () {
    it('returns product_groups as entity type', function () {
        $registry = new ProductGroupColumnRegistry;

        expect($registry->entityType())->toBe('product_groups');
    });

    it('returns ProductGroup model class', function () {
        $registry = new ProductGroupColumnRegistry;

        expect($registry->modelClass())->toBe(ProductGroup::class);
    });

    it('returns all product group columns', function () {
        $registry = new ProductGroupColumnRegistry;
        $columns = $registry->allColumns();

        expect($columns)->toHaveKey('name')
            ->and($columns)->toHaveKey('description')
            ->and($columns)->toHaveKey('products_count')
            ->and($columns)->toHaveKey('created_at')
            ->and($columns['name'])->toBeInstanceOf(Column::class);
    });

    it('defines default columns', function () {
        $registry = new ProductGroupColumnRegistry;
        $defaults = $registry->defaultColumns();

        expect($defaults)->toContain('name', 'description', 'parent_id', 'products_count', 'created_at');
    });

    it('includes the parent column in default columns', function () {
        $registry = new ProductGroupColumnRegistry;

        expect($registry->defaultColumns())->toContain('parent_id');
    });

    it('returns default columns as subset of all columns', function () {
        $registry = new ProductGroupColumnRegistry;
        $allKeys = array_keys($registry->allColumns());
        $defaults = $registry->defaultColumns();

        foreach ($defaults as $default) {
            expect($allKeys)->toContain($default);
        }
    });

    it('gets a specific column', function () {
        $registry = new ProductGroupColumnRegistry;
        $column = $registry->get('name');

        expect($column)->not->toBeNull()
            ->and($column)->toBeInstanceOf(Column::class)
            ->and($column->label)->toBe('Name')
            ->and($column->sortable)->toBeTrue();
    });

    it('returns null for unknown column', function () {
        $registry = new ProductGroupColumnRegistry;
        expect($registry->get('nonexistent'))->toBeNull();
    });
});

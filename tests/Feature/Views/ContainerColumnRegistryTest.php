<?php

use App\Views\Column;
use App\Views\ContainerColumnRegistry;

describe('ContainerColumnRegistry', function () {
    it('exposes the container columns with the right metadata', function () {
        $registry = app(ContainerColumnRegistry::class);
        $columns = $registry->allColumns();

        expect($registry->entityType())->toBe('containers')
            ->and($columns)->toHaveKeys(['name', 'barcode', 'status', 'is_temporary', 'product_id'])
            ->and($columns['name'])->toBeInstanceOf(Column::class)
            ->and($columns['name']->sortable)->toBeTrue()
            ->and($columns['name']->filterable)->toBeTrue()
            ->and($columns['status']->type)->toBe('enum')
            ->and($columns['is_temporary']->type)->toBe('boolean')
            ->and($columns['product_id']->type)->toBe('relation');
    });

    it('returns default columns that are a subset of the declared columns', function () {
        $registry = app(ContainerColumnRegistry::class);

        expect($registry->defaultColumns())
            ->each->toBeIn(array_keys($registry->allColumns()));
    });
});

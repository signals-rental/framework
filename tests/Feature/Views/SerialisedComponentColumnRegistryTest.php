<?php

use App\Views\Column;
use App\Views\SerialisedComponentColumnRegistry;

describe('SerialisedComponentColumnRegistry', function () {
    it('exposes the kit-component columns with the right metadata', function () {
        $registry = app(SerialisedComponentColumnRegistry::class);
        $columns = $registry->allColumns();

        expect($registry->entityType())->toBe('serialised_components')
            ->and($columns)->toHaveKeys(['product_id', 'component_product_id', 'quantity', 'binding'])
            ->and($columns['product_id'])->toBeInstanceOf(Column::class)
            ->and($columns['product_id']->type)->toBe('relation')
            ->and($columns['component_product_id']->type)->toBe('relation')
            ->and($columns['quantity']->type)->toBe('number')
            ->and($columns['binding']->type)->toBe('enum');
    });

    it('returns default columns that are a subset of the declared columns', function () {
        $registry = app(SerialisedComponentColumnRegistry::class);

        expect($registry->defaultColumns())
            ->each->toBeIn(array_keys($registry->allColumns()));
    });
});

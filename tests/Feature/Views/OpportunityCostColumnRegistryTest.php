<?php

use App\Views\Column;
use App\Views\OpportunityCostColumnRegistry;

describe('OpportunityCostColumnRegistry', function () {
    it('exposes the cost columns with the right metadata', function () {
        $registry = app(OpportunityCostColumnRegistry::class);
        $columns = $registry->allColumns();

        expect($registry->entityType())->toBe('opportunity_costs')
            ->and($columns)->toHaveKeys(['description', 'cost_type', 'transaction_type', 'amount', 'quantity'])
            ->and($columns['description'])->toBeInstanceOf(Column::class)
            ->and($columns['cost_type']->type)->toBe('enum')
            ->and($columns['amount']->type)->toBe('money')
            ->and($columns['quantity']->type)->toBe('number')
            ->and($columns['is_optional']->type)->toBe('boolean');
    });

    it('returns default columns that are a subset of the declared columns', function () {
        $registry = app(OpportunityCostColumnRegistry::class);

        expect($registry->defaultColumns())
            ->each->toBeIn(array_keys($registry->allColumns()));
    });
});

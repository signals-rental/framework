<?php

use App\Views\Column;
use App\Views\OpportunityItemColumnRegistry;

describe('OpportunityItemColumnRegistry', function () {
    it('exposes the line-item columns with the right metadata', function () {
        $registry = app(OpportunityItemColumnRegistry::class);
        $columns = $registry->allColumns();

        expect($registry->entityType())->toBe('opportunity_items')
            ->and($columns)->toHaveKeys(['name', 'item_type', 'quantity', 'unit_price', 'total', 'starts_at'])
            ->and($columns['name'])->toBeInstanceOf(Column::class)
            ->and($columns['item_type']->type)->toBe('enum')
            ->and($columns['unit_price']->type)->toBe('money')
            ->and($columns['total']->type)->toBe('money')
            ->and($columns['starts_at']->type)->toBe('datetime')
            ->and($columns['is_optional']->type)->toBe('boolean');
    });

    it('returns default columns that are a subset of the declared columns', function () {
        $registry = app(OpportunityItemColumnRegistry::class);

        expect($registry->defaultColumns())
            ->each->toBeIn(array_keys($registry->allColumns()));
    });
});

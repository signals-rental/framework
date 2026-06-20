<?php

use App\Models\Opportunity;
use App\Services\SchemaBuilder;
use App\Views\Column;
use App\Views\OpportunityColumnRegistry;

/**
 * R-A master M14 (schema enum types) + M18 (ColumnRegistry/defineSchema
 * completeness).
 */
describe('Opportunity::defineSchema', function () {
    it('reports state and status as enum fields so the Form renders dropdowns', function () {
        $builder = new SchemaBuilder;
        Opportunity::defineSchema($builder);
        $fields = $builder->build();

        expect($fields['state']->type)->toBe('enum')
            ->and($fields['status']->type)->toBe('enum');
    });

    it('exposes the charge-date fields in the schema', function () {
        $builder = new SchemaBuilder;
        Opportunity::defineSchema($builder);
        $fields = $builder->build();

        expect($fields)->toHaveKeys(['charge_starts_at', 'charge_ends_at'])
            ->and($fields['charge_starts_at']->type)->toBe('datetime')
            ->and($fields['charge_ends_at']->type)->toBe('datetime');
    });

    it('exposes has_shortage as a filterable boolean schema field', function () {
        $builder = new SchemaBuilder;
        Opportunity::defineSchema($builder);
        $fields = $builder->build();

        expect($fields)->toHaveKey('has_shortage')
            ->and($fields['has_shortage']->type)->toBe('boolean')
            ->and($fields['has_shortage']->filterable)->toBeTrue();
    });
});

describe('OpportunityColumnRegistry', function () {
    it('exposes the R-A columns with the right flags', function () {
        $registry = new OpportunityColumnRegistry;
        $columns = $registry->allColumns();

        expect($columns)->toHaveKeys([
            'tag_list', 'has_shortage', 'version_count', 'active_version_id',
            'charge_starts_at', 'charge_ends_at',
        ]);

        expect($columns['has_shortage'])->toBeInstanceOf(Column::class)
            ->and($columns['has_shortage']->type)->toBe('boolean')
            ->and($columns['has_shortage']->filterable)->toBeTrue()
            ->and($columns['has_shortage']->sortable)->toBeTrue();

        expect($columns['tag_list']->type)->toBe('json')
            ->and($columns['tag_list']->filterable)->toBeTrue()
            ->and($columns['version_count']->type)->toBe('integer')
            ->and($columns['version_count']->sortable)->toBeTrue();
    });
});

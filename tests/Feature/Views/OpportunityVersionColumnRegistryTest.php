<?php

use App\Models\OpportunityVersion;
use App\Services\SchemaBuilder;
use App\Views\Column;
use App\Views\OpportunityVersionColumnRegistry;

/**
 * R-D master M15 — OpportunityVersion::defineSchema + ColumnRegistry must expose
 * the version dispatch/decline fields so the version detail tab can introspect and
 * display them.
 */
describe('OpportunityVersion::defineSchema', function () {
    it('exposes the dispatch and decline fields with the right types', function () {
        $builder = new SchemaBuilder;
        OpportunityVersion::defineSchema($builder);
        $fields = $builder->build();

        expect($fields)->toHaveKeys([
            'sent_to', 'sent_via', 'accepted_by', 'decline_reason', 'declined_at', 'notes',
        ]);

        expect($fields['sent_to']->type)->toBe('relation')
            ->and($fields['sent_via']->type)->toBe('string')
            ->and($fields['accepted_by']->type)->toBe('relation')
            ->and($fields['decline_reason']->type)->toBe('text')
            ->and($fields['declined_at']->type)->toBe('datetime')
            ->and($fields['notes']->type)->toBe('text');
    });
});

describe('OpportunityVersionColumnRegistry', function () {
    it('exposes the dispatch and decline columns', function () {
        $registry = new OpportunityVersionColumnRegistry;
        $columns = $registry->allColumns();

        expect($columns)->toHaveKeys([
            'sent_to', 'sent_via', 'accepted_by', 'decline_reason', 'declined_at', 'notes',
        ]);

        expect($columns['sent_to'])->toBeInstanceOf(Column::class)
            ->and($columns['sent_to']->type)->toBe('relation')
            ->and($columns['sent_to']->filterable)->toBeTrue()
            ->and($columns['decline_reason']->filterable)->toBeTrue()
            ->and($columns['accepted_by']->type)->toBe('relation')
            ->and($columns['declined_at']->type)->toBe('datetime');
    });
});

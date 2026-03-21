<?php

use App\Views\ActivityColumnRegistry;
use App\Views\Column;

describe('ActivityColumnRegistry', function () {
    it('returns activities as entity type', function () {
        $registry = new ActivityColumnRegistry;

        expect($registry->entityType())->toBe('activities');
    });

    it('returns Activity model class', function () {
        $registry = new ActivityColumnRegistry;

        expect($registry->modelClass())->toBe(\App\Models\Activity::class);
    });

    it('returns all activity columns', function () {
        $registry = new ActivityColumnRegistry;
        $columns = $registry->allColumns();

        expect($columns)->toHaveKey('subject')
            ->and($columns)->toHaveKey('type_id')
            ->and($columns)->toHaveKey('status_id')
            ->and($columns)->toHaveKey('priority')
            ->and($columns)->toHaveKey('completed')
            ->and($columns)->toHaveKey('created_at')
            ->and($columns['subject'])->toBeInstanceOf(Column::class);
    });

    it('defines default columns', function () {
        $registry = new ActivityColumnRegistry;
        $defaults = $registry->defaultColumns();

        expect($defaults)->toContain('subject', 'type_id', 'status_id', 'starts_at', 'created_at');
    });

    it('returns default columns as subset of all columns', function () {
        $registry = new ActivityColumnRegistry;
        $allKeys = array_keys($registry->allColumns());
        $defaults = $registry->defaultColumns();

        foreach ($defaults as $default) {
            expect($allKeys)->toContain($default);
        }
    });

    it('validates column keys', function () {
        $registry = new ActivityColumnRegistry;
        $invalid = $registry->validateColumns(['subject', 'nonexistent']);
        expect($invalid)->toBe(['nonexistent']);
    });

    it('gets a specific column', function () {
        $registry = new ActivityColumnRegistry;
        $column = $registry->get('subject');

        expect($column)->not->toBeNull()
            ->and($column)->toBeInstanceOf(Column::class)
            ->and($column->label)->toBe('Subject')
            ->and($column->sortable)->toBeTrue();
    });

    it('returns correct column types', function () {
        $registry = new ActivityColumnRegistry;

        expect($registry->get('type_id')->type)->toBe('enum')
            ->and($registry->get('status_id')->type)->toBe('enum')
            ->and($registry->get('completed')->type)->toBe('boolean')
            ->and($registry->get('created_at')->type)->toBe('datetime');
    });

    it('returns null for unknown column', function () {
        $registry = new ActivityColumnRegistry;
        expect($registry->get('nonexistent'))->toBeNull();
    });
});

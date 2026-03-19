<?php

use App\Views\Column;
use App\Views\MemberColumnRegistry;

describe('Column', function () {
    it('builds a column with fluent API', function () {
        $column = Column::make('name')
            ->label('Full Name')
            ->sortable()
            ->filterable()
            ->type('string');

        expect($column->key)->toBe('name')
            ->and($column->label)->toBe('Full Name')
            ->and($column->sortable)->toBeTrue()
            ->and($column->filterable)->toBeTrue()
            ->and($column->type)->toBe('string');
    });

    it('generates label from key when not provided', function () {
        $column = Column::make('first_name');

        expect($column->label)->toBe('First Name');
    });

    it('generates label from single word key', function () {
        $column = Column::make('email');

        expect($column->label)->toBe('Email');
    });

    it('uses explicit label when provided', function () {
        $column = Column::make('cost_per_hour')->label('Cost/Hour');

        expect($column->label)->toBe('Cost/Hour');
    });

    it('defaults to not sortable', function () {
        $column = Column::make('name');

        expect($column->sortable)->toBeFalse();
    });

    it('defaults to not filterable', function () {
        $column = Column::make('name');

        expect($column->filterable)->toBeFalse();
    });

    it('defaults to string type', function () {
        $column = Column::make('name');

        expect($column->type)->toBe('string');
    });

    it('defaults to null width', function () {
        $column = Column::make('name');

        expect($column->width)->toBeNull();
    });

    it('sets width via fluent API', function () {
        $column = Column::make('name')->width(200);

        expect($column->width)->toBe(200);
    });

    it('can disable sortable', function () {
        $column = Column::make('name')->sortable(false);

        expect($column->sortable)->toBeFalse();
    });

    it('can disable filterable', function () {
        $column = Column::make('name')->filterable(false);

        expect($column->filterable)->toBeFalse();
    });

    it('supports different types', function () {
        expect(Column::make('a')->type('boolean')->type)->toBe('boolean')
            ->and(Column::make('b')->type('enum')->type)->toBe('enum')
            ->and(Column::make('c')->type('datetime')->type)->toBe('datetime')
            ->and(Column::make('d')->type('money')->type)->toBe('money')
            ->and(Column::make('e')->type('tags')->type)->toBe('tags');
    });
});

describe('MemberColumnRegistry', function () {
    it('returns members as entity type', function () {
        $registry = new MemberColumnRegistry;

        expect($registry->entityType())->toBe('members');
    });

    it('returns all member columns', function () {
        $registry = new MemberColumnRegistry;
        $columns = $registry->allColumns();

        expect($columns)->toHaveKey('name')
            ->and($columns)->toHaveKey('membership_type')
            ->and($columns)->toHaveKey('email')
            ->and($columns)->toHaveKey('phone')
            ->and($columns)->toHaveKey('is_active')
            ->and($columns)->toHaveKey('created_at')
            ->and($columns)->toHaveKey('updated_at')
            ->and($columns['name'])->toBeInstanceOf(Column::class);
    });

    it('returns default columns for members', function () {
        $registry = new MemberColumnRegistry;
        $defaults = $registry->defaultColumns();

        expect($defaults)->toContain('name', 'membership_type', 'email', 'phone', 'is_active', 'created_at');
    });

    it('returns default columns as subset of all columns', function () {
        $registry = new MemberColumnRegistry;
        $allKeys = array_keys($registry->allColumns());
        $defaults = $registry->defaultColumns();

        foreach ($defaults as $default) {
            expect($allKeys)->toContain($default);
        }
    });

    it('validates column keys with all valid keys', function () {
        $registry = new MemberColumnRegistry;
        $invalid = $registry->validateColumns(['name', 'email', 'phone']);

        expect($invalid)->toBeEmpty();
    });

    it('validates column keys returning invalid ones', function () {
        $registry = new MemberColumnRegistry;
        $invalid = $registry->validateColumns(['name', 'nonexistent_column', 'another_fake']);

        expect($invalid)->toBe(['nonexistent_column', 'another_fake']);
    });

    it('validates empty column list', function () {
        $registry = new MemberColumnRegistry;
        $invalid = $registry->validateColumns([]);

        expect($invalid)->toBeEmpty();
    });

    it('gets a specific column', function () {
        $registry = new MemberColumnRegistry;
        $column = $registry->get('name');

        expect($column)->not->toBeNull()
            ->and($column)->toBeInstanceOf(Column::class)
            ->and($column->label)->toBe('Name')
            ->and($column->sortable)->toBeTrue();
    });

    it('gets column with correct type', function () {
        $registry = new MemberColumnRegistry;

        expect($registry->get('membership_type')->type)->toBe('enum')
            ->and($registry->get('is_active')->type)->toBe('boolean')
            ->and($registry->get('created_at')->type)->toBe('datetime')
            ->and($registry->get('cost_per_hour')->type)->toBe('money')
            ->and($registry->get('tags')->type)->toBe('tags');
    });

    it('returns null for unknown column', function () {
        $registry = new MemberColumnRegistry;

        expect($registry->get('nonexistent'))->toBeNull();
    });

    it('returns columns with correct sortable settings', function () {
        $registry = new MemberColumnRegistry;

        expect($registry->get('name')->sortable)->toBeTrue()
            ->and($registry->get('phone')->sortable)->toBeFalse()
            ->and($registry->get('description')->sortable)->toBeFalse()
            ->and($registry->get('created_at')->sortable)->toBeTrue();
    });

    it('returns columns with correct filterable settings', function () {
        $registry = new MemberColumnRegistry;

        expect($registry->get('name')->filterable)->toBeTrue()
            ->and($registry->get('phone')->filterable)->toBeTrue()
            ->and($registry->get('description')->filterable)->toBeFalse()
            ->and($registry->get('created_at')->filterable)->toBeFalse();
    });

    it('caches columns after first boot', function () {
        $registry = new MemberColumnRegistry;

        // Call allColumns twice - should return same data
        $first = $registry->allColumns();
        $second = $registry->allColumns();

        expect($first)->toBe($second);
    });
});

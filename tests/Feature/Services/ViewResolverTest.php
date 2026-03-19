<?php

use App\Models\CustomView;
use App\Models\Member;
use App\Models\User;
use App\Models\UserViewPreference;
use App\Services\ViewResolver;
use Database\Seeders\ViewSeeder;

beforeEach(function () {
    $this->seed(ViewSeeder::class);
    $this->resolver = app(ViewResolver::class);
    $this->user = User::factory()->create();
});

it('resolves explicit view by ID', function () {
    $view = CustomView::factory()->create(['entity_type' => 'members']);

    $resolved = $this->resolver->resolve('members', $view->id, $this->user);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($view->id);
});

it('ignores explicit view ID for wrong entity type', function () {
    $view = CustomView::factory()->create(['entity_type' => 'opportunities']);

    $resolved = $this->resolver->resolve('members', $view->id, $this->user);

    // Should fall through to system default since the view is for a different entity
    expect($resolved->name)->toBe('All Members');
});

it('resolves user preference when no explicit ID', function () {
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'user_id' => $this->user->id,
    ]);

    UserViewPreference::create([
        'user_id' => $this->user->id,
        'entity_type' => 'members',
        'custom_view_id' => $view->id,
    ]);

    $resolved = $this->resolver->resolve('members', null, $this->user);

    expect($resolved->id)->toBe($view->id);
});

it('falls back to system default when no preference', function () {
    $resolved = $this->resolver->resolve('members', null, $this->user);

    expect($resolved)->not->toBeNull()
        ->and($resolved->name)->toBe('All Members')
        ->and($resolved->is_default)->toBeTrue();
});

it('falls back to system default when preference view is deleted', function () {
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'user_id' => $this->user->id,
    ]);

    UserViewPreference::create([
        'user_id' => $this->user->id,
        'entity_type' => 'members',
        'custom_view_id' => $view->id,
    ]);

    $view->delete();

    $resolved = $this->resolver->resolve('members', null, $this->user);

    expect($resolved->name)->toBe('All Members');
});

it('returns null for unknown entity type', function () {
    $resolved = $this->resolver->resolve('unknown_entity', null, $this->user);

    expect($resolved)->toBeNull();
});

it('returns null for unknown entity type with null user', function () {
    $resolved = $this->resolver->resolve('unknown_entity', null, null);

    expect($resolved)->toBeNull();
});

it('resolves system default when user is null', function () {
    $resolved = $this->resolver->resolve('members', null, null);

    expect($resolved)->not->toBeNull()
        ->and($resolved->name)->toBe('All Members');
});

it('applies view sort to query', function () {
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'sort_column' => 'name',
        'sort_direction' => 'desc',
    ]);

    $query = Member::query();
    $this->resolver->applySort($query, $view);

    expect($query->toSql())->toContain('order by');
});

it('applies ascending sort direction', function () {
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'sort_column' => 'name',
        'sort_direction' => 'asc',
    ]);

    $query = Member::query();
    $this->resolver->applySort($query, $view);

    expect($query->getQuery()->orders)->toHaveCount(1)
        ->and($query->getQuery()->orders[0]['direction'])->toBe('asc');
});

it('does not apply sort when sort_column is null', function () {
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'sort_column' => null,
    ]);

    $query = Member::query();
    $this->resolver->applySort($query, $view);

    expect($query->getQuery()->orders)->toBeNull();
});

it('defaults to asc for invalid sort direction', function () {
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'sort_column' => 'name',
        'sort_direction' => 'invalid',
    ]);

    $query = Member::query();
    $this->resolver->applySort($query, $view);

    expect($query->getQuery()->orders[0]['direction'])->toBe('asc');
});

it('applies view filters to query', function () {
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'filters' => [
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'organisation'],
        ],
    ]);

    $query = Member::query();
    $this->resolver->applyFilters($query, $view);

    expect($query->toSql())->toContain('where');
});

it('merges explicit params with view filters', function () {
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'filters' => [
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'organisation'],
            ['field' => 'is_active', 'predicate' => 'eq', 'value' => true],
        ],
    ]);

    $query = Member::query();
    // Explicit param should override the view filter for membership_type
    $this->resolver->applyFilters($query, $view, ['membership_type_eq' => 'contact']);

    expect($query->toSql())->toContain('where');
});

it('does not apply filters when view has empty filters', function () {
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'filters' => [],
    ]);

    $query = Member::query();
    $beforeSql = $query->toSql();
    $this->resolver->applyFilters($query, $view);

    expect($query->toSql())->toBe($beforeSql);
});

it('applies OR logic between filters', function () {
    Member::factory()->create(['name' => 'Alpha Corp', 'membership_type' => 'organisation']);
    Member::factory()->create(['name' => 'Beta Ltd', 'membership_type' => 'contact']);
    Member::factory()->create(['name' => 'Gamma Inc', 'membership_type' => 'venue']);

    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'filters' => [
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'organisation', 'logic' => 'and'],
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'contact', 'logic' => 'or'],
        ],
    ]);

    $query = Member::query();
    $this->resolver->applyFilters($query, $view);
    $results = $query->get();

    expect($results->pluck('membership_type')->map(fn ($t) => $t->value)->unique()->sort()->values()->all())
        ->toBe(['contact', 'organisation']);
});

it('applies NAND logic between filters', function () {
    Member::factory()->create(['name' => 'Active Org', 'membership_type' => 'organisation', 'is_active' => true]);
    Member::factory()->create(['name' => 'Inactive Org', 'membership_type' => 'organisation', 'is_active' => false]);

    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'filters' => [
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'organisation', 'logic' => 'and'],
            ['field' => 'is_active', 'predicate' => 'eq', 'value' => false, 'logic' => 'nand'],
        ],
    ]);

    $query = Member::query();
    $this->resolver->applyFilters($query, $view);
    $results = $query->get();

    // NAND = not(is_active=false), so only active org
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Active Org');
});

it('applies NOR logic between filters', function () {
    Member::factory()->create(['name' => 'Active Org', 'membership_type' => 'organisation', 'is_active' => true]);
    Member::factory()->create(['name' => 'Active Contact', 'membership_type' => 'contact', 'is_active' => true]);
    Member::factory()->create(['name' => 'Inactive Venue', 'membership_type' => 'venue', 'is_active' => false]);

    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'filters' => [
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'organisation', 'logic' => 'and'],
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'contact', 'logic' => 'nor'],
        ],
    ]);

    $query = Member::query();
    $this->resolver->applyFilters($query, $view);
    $results = $query->get();

    // org OR NOT(contact) => org + venue
    $types = $results->pluck('membership_type')->map(fn ($t) => $t->value)->unique()->sort()->values()->all();
    expect($types)->toContain('organisation');
    expect($types)->toContain('venue');
});

it('skips filters with null field or value', function () {
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'filters' => [
            ['field' => null, 'predicate' => 'eq', 'value' => 'test'],
            ['field' => 'name', 'predicate' => 'eq', 'value' => null],
        ],
    ]);

    $query = Member::query();
    $beforeSql = $query->toSql();
    $this->resolver->applyFilters($query, $view);

    expect($query->toSql())->toBe($beforeSql);
});

it('explicit params override matching view filters', function () {
    Member::factory()->create(['name' => 'Org One', 'membership_type' => 'organisation']);
    Member::factory()->create(['name' => 'Contact One', 'membership_type' => 'contact']);

    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'filters' => [
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'organisation'],
        ],
    ]);

    $query = Member::query();
    // Explicit param overrides the view filter
    $this->resolver->applyFilters($query, $view, ['membership_type_eq' => 'contact']);
    $results = $query->get();

    expect($results->every(fn (Member $m): bool => $m->getRawOriginal('membership_type') === 'contact'))->toBeTrue();
});

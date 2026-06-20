<?php

use App\Models\CustomField;
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

it('applies AND logic between non-first filters', function () {
    Member::factory()->create(['name' => 'Active Org', 'membership_type' => 'organisation', 'is_active' => true]);
    Member::factory()->create(['name' => 'Inactive Org', 'membership_type' => 'organisation', 'is_active' => false]);
    Member::factory()->create(['name' => 'Active Contact', 'membership_type' => 'contact', 'is_active' => true]);

    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'filters' => [
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'organisation', 'logic' => 'and'],
            ['field' => 'is_active', 'predicate' => 'eq', 'value' => true, 'logic' => 'and'],
        ],
    ]);

    $query = Member::query();
    $this->resolver->applyFilters($query, $view);
    $results = $query->get();

    // organisation AND is_active=true => only "Active Org"
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Active Org');
});

it('does not apply sort for an invalid sort column name', function () {
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'sort_column' => 'name; DROP TABLE members',
    ]);

    $query = Member::query();
    $beforeSql = $query->toSql();
    $this->resolver->applySort($query, $view);

    // The unsafe column name fails the identifier regex, so no order-by is added.
    expect($query->getQuery()->orders)->toBeNull()
        ->and($query->toSql())->toBe($beforeSql);
});

it('skips a custom-field (cf.*) sort column instead of crashing', function () {
    // Regression (M13): a cf.* sort column was emitted as orderBy('cf.field'),
    // producing ORDER BY "cf"."field" and a fatal 500 on PostgreSQL. cf.* sorts
    // need an EAV subquery join that is not modelled here, so they must be skipped
    // and the query left to its default ordering.
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'sort_column' => 'cf.po_reference',
        'sort_direction' => 'desc',
    ]);

    $query = Member::query();
    $beforeSql = $query->toSql();
    $this->resolver->applySort($query, $view);

    expect($query->getQuery()->orders)->toBeNull()
        ->and($query->toSql())->toBe($beforeSql);
});

it('leaves a scalar sort column unchanged when a cf.* guard is present', function () {
    // The cf.* skip must not affect ordinary core-column sorts.
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'sort_column' => 'name',
        'sort_direction' => 'desc',
    ]);

    $query = Member::query();
    $this->resolver->applySort($query, $view);

    expect($query->getQuery()->orders)->toHaveCount(1)
        ->and($query->getQuery()->orders[0]['column'])->toBe('name')
        ->and($query->getQuery()->orders[0]['direction'])->toBe('desc');
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
    $this->resolver->applyFilters($query, $view, ['membership_type_eq' => 'contact'], ['membership_type']);

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

it('ignores explicit params whose field is not whitelisted', function () {
    $view = CustomView::factory()->create(['entity_type' => 'members', 'filters' => []]);

    $query = Member::query();
    $beforeSql = $query->toSql();
    // membership_type is supplied but only `name` is whitelisted -> the param is dropped,
    // so no where clause is added to the query.
    $this->resolver->applyFilters($query, $view, ['membership_type_eq' => 'contact'], ['name']);

    expect($query->toSql())->toBe($beforeSql);
});

it('applies an explicit cf. custom-field filter alongside an active view', function () {
    // Regression: with a view active, an explicit ?q[cf.<field>_eq] filter was
    // silently dropped — both by whitelistParams() discarding the cf. key and by
    // the explicit-params apply() call not receiving the custom-field module.
    $field = CustomField::factory()->forModule('Member')->string()->create([
        'name' => 'po_reference',
        'is_searchable' => true,
        'is_active' => true,
    ]);

    $match = Member::factory()->create(['name' => 'Acme Ltd']);
    $other = Member::factory()->create(['name' => 'Globex Inc']);
    $match->customFieldValues()->create(['custom_field_id' => $field->id, 'value_string' => 'PO-123']);
    $other->customFieldValues()->create(['custom_field_id' => $field->id, 'value_string' => 'PO-999']);

    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'filters' => [],
    ]);

    $query = Member::query();
    $this->resolver->applyFilters($query, $view, ['cf.po_reference_eq' => 'PO-123']);
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($match->id);
});

it('keeps a cf. param through whitelisting even when not in the allowed fields list', function () {
    // The cf. key must survive whitelistParams() without being listed in
    // $allowedExplicitFields, since custom fields are validated downstream.
    $field = CustomField::factory()->forModule('Member')->string()->create([
        'name' => 'po_reference',
        'is_searchable' => true,
        'is_active' => true,
    ]);

    $match = Member::factory()->create();
    Member::factory()->create();
    $match->customFieldValues()->create(['custom_field_id' => $field->id, 'value_string' => 'PO-123']);

    $view = CustomView::factory()->create(['entity_type' => 'members', 'filters' => []]);

    $query = Member::query();
    // Only `name` is whitelisted; the cf. key must still pass through.
    $this->resolver->applyFilters($query, $view, ['cf.po_reference_eq' => 'PO-123'], ['name']);
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($match->id);
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
    $this->resolver->applyFilters($query, $view, ['membership_type_eq' => 'contact'], ['membership_type']);
    $results = $query->get();

    expect($results->every(fn (Member $m): bool => $m->getRawOriginal('membership_type') === 'contact'))->toBeTrue();
});

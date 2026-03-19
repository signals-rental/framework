<?php

use App\Enums\MembershipType;
use App\Livewire\Components\DataTable;
use App\Models\Member;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(User::factory()->create());
});

/**
 * @return array<int, array<string, mixed>>
 */
function memberColumns(): array
{
    return [
        ['key' => 'checkbox', 'type' => 'checkbox'],
        ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text'],
        ['key' => 'membership_type', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => ['contact' => 'Contact', 'organisation' => 'Organisation', 'venue' => 'Venue']],
        ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
        ['key' => 'actions', 'type' => 'actions'],
    ];
}

it('renders the data table with items', function () {
    Member::factory()->count(3)->create();
    $total = Member::count();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'searchable' => ['name'],
    ])
        ->assertStatus(200)
        ->assertViewHas('items', fn ($items) => $items->count() === $total);
});

it('sorts by column ascending and descending', function () {
    Member::factory()->create(['name' => 'Zebra Corp']);
    Member::factory()->create(['name' => 'Alpha Inc']);

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ]);

    $component->call('sortBy', 'name');
    $items = $component->viewData('items');
    expect($items->first()->name)->toBe('Alpha Inc');

    $component->call('sortBy', 'name');
    $items = $component->viewData('items');
    expect($items->first()->name)->toBe('Zebra Corp');
});

it('searches across searchable columns', function () {
    if (config('database.default') === 'sqlite') {
        $this->markTestSkipped('Search uses PostgreSQL ilike operator');
    }

    Member::factory()->create(['name' => 'Acme Events']);
    Member::factory()->create(['name' => 'Beta Sound']);

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'searchable' => ['name'],
    ])
        ->set('search', 'acme')
        ->assertViewHas('items', fn ($items) => $items->count() === 1 && $items->first()->name === 'Acme Events');
});

it('filters by select column', function () {
    Member::factory()->create(['membership_type' => MembershipType::Contact]);
    Member::factory()->create(['membership_type' => MembershipType::Organisation]);
    Member::factory()->create(['membership_type' => MembershipType::Organisation]);

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('applyFilter', 'membership_type', 'organisation')
        ->assertViewHas('items', fn ($items) => $items->count() === 2);
});

it('clears a single filter', function () {
    Member::factory()->count(3)->create();
    $total = Member::count();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('applyFilter', 'membership_type', 'contact')
        ->call('clearFilter', 'membership_type')
        ->assertViewHas('items', fn ($items) => $items->count() === $total);
});

it('clears all filters and search', function () {
    if (config('database.default') === 'sqlite') {
        $this->markTestSkipped('Search uses PostgreSQL ilike operator');
    }

    Member::factory()->count(3)->create();
    $total = Member::count();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'searchable' => ['name'],
    ])
        ->set('search', 'xyz')
        ->call('applyFilter', 'membership_type', 'contact')
        ->call('clearAllFilters')
        ->assertSet('search', '')
        ->assertSet('filters', [])
        ->assertViewHas('items', fn ($items) => $items->count() === $total);
});

it('toggles individual row selection', function () {
    $member = Member::factory()->create();

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ]);

    $component->call('toggleSelected', $member->id)
        ->assertSet('selected', [$member->id]);

    $component->call('toggleSelected', $member->id)
        ->assertSet('selected', []);
});

it('toggles select all on current page', function () {
    Member::factory()->count(3)->create();
    $total = Member::count();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'perPage' => 25,
    ])
        ->call('toggleSelectAll')
        ->assertSet('selectAll', true)
        ->assertViewHas('items', fn ($items) => $items->count() === $total);
});

it('clears selection', function () {
    $member = Member::factory()->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('toggleSelected', $member->id)
        ->call('clearSelection')
        ->assertSet('selected', [])
        ->assertSet('selectAll', false);
});

it('paginates results', function () {
    Member::factory()->count(30)->create();
    $total = Member::count();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'perPage' => 10,
    ])
        ->assertViewHas('items', fn ($items) => $items->count() === 10 && $items->total() === $total);
});

it('applies default sort', function () {
    Member::factory()->create(['name' => 'Zebra']);
    Member::factory()->create(['name' => 'Alpha']);

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'defaultSort' => 'name',
        'defaultDirection' => 'asc',
    ])
        ->assertViewHas('items', fn ($items) => $items->first()->name === 'Alpha');
});

it('applies eager loading via with parameter', function () {
    Member::factory()->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'with' => ['emails', 'phones'],
    ])
        ->assertStatus(200);
});

it('shift-selects all rows between last selected and clicked row', function () {
    $members = Member::factory()->count(5)->create();
    $ids = $members->sortBy('id')->pluck('id')->values()->all();

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'defaultSort' => 'id',
        'defaultDirection' => 'asc',
    ]);

    // First click sets lastSelectedId
    $component->call('toggleSelected', $ids[1]);
    expect($component->get('selected'))->toBe([$ids[1]]);
    expect($component->get('lastSelectedId'))->toBe($ids[1]);

    // Shift-click on row 4 should select rows 1-4 (indices 1 through 3)
    $component->call('shiftSelect', $ids[3], $ids);
    $selected = $component->get('selected');
    sort($selected);
    expect($selected)->toBe([$ids[1], $ids[2], $ids[3]]);
    expect($component->get('lastSelectedId'))->toBe($ids[3]);
});

it('shift-selects backwards when clicking above the last selected row', function () {
    $members = Member::factory()->count(5)->create();
    $ids = $members->sortBy('id')->pluck('id')->values()->all();

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'defaultSort' => 'id',
        'defaultDirection' => 'asc',
    ]);

    // Click row 4 first
    $component->call('toggleSelected', $ids[3]);

    // Shift-click row 1 — should select 1 through 4
    $component->call('shiftSelect', $ids[0], $ids);
    $selected = $component->get('selected');
    sort($selected);
    expect($selected)->toBe([$ids[0], $ids[1], $ids[2], $ids[3]]);
});

it('shift-select falls back to toggle when no previous selection', function () {
    $member = Member::factory()->create();

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ]);

    $component->call('shiftSelect', $member->id, [$member->id]);
    expect($component->get('selected'))->toBe([$member->id]);
    expect($component->get('lastSelectedId'))->toBe($member->id);
});

it('clears lastSelectedId when deselecting a row', function () {
    $member = Member::factory()->create();

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ]);

    $component->call('toggleSelected', $member->id);
    expect($component->get('lastSelectedId'))->toBe($member->id);

    $component->call('toggleSelected', $member->id);
    expect($component->get('lastSelectedId'))->toBeNull();
});

it('renders empty state when no results', function () {
    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'scopes' => ['ofType' => MembershipType::Venue],
        'emptyMessage' => 'No members found.',
    ])
        ->assertSee('No members found.');
});

it('rejects invalid perPage values', function () {
    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('setPerPage', 999)
        ->assertSet('perPage', 12)
        ->call('setPerPage', 0)
        ->assertSet('perPage', 12)
        ->call('setPerPage', -1)
        ->assertSet('perPage', 12);
});

it('accepts valid perPage values', function () {
    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('setPerPage', 24)
        ->assertSet('perPage', 24)
        ->call('setPerPage', 48)
        ->assertSet('perPage', 48);
});

it('ignores sort on non-sortable columns', function () {
    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'defaultSort' => 'name',
    ])
        ->call('sortBy', 'actions')
        ->assertSet('sortField', 'name');
});

it('ignores filters on non-filterable columns', function () {
    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('applyFilter', 'is_active', 'true')
        ->assertSet('filters', []);
});

it('resets selection on configured refresh event', function () {
    $member = Member::factory()->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'refreshEvents' => ['member-deleted'],
    ])
        ->call('toggleSelected', $member->id)
        ->assertSet('selected', [$member->id])
        ->dispatch('member-deleted')
        ->assertSet('selected', [])
        ->assertSet('selectAll', false)
        ->assertSet('lastSelectedId', null);
});

it('throws when dispatching events not in refreshEvents', function () {
    $member = Member::factory()->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'refreshEvents' => ['member-deleted'],
    ])
        ->call('toggleSelected', $member->id)
        ->assertSet('selected', [$member->id])
        ->dispatch('some-other-event');
})->throws(\Livewire\Exceptions\EventHandlerDoesNotExist::class);

it('throws exception for invalid model class', function () {
    expect(fn () => Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => 'NonExistentModel',
    ]))->toThrow(\Illuminate\View\ViewException::class);
});

it('caps search input at 200 characters', function () {
    $longSearch = str_repeat('a', 300);

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'searchable' => ['name'],
    ])
        ->set('search', $longSearch)
        ->assertSet('search', str_repeat('a', 200));
})->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

it('validates perPage against allowed options on mount', function () {
    Member::factory()->count(3)->create();

    // Simulate a URL-bound value outside the allowed options
    Livewire::withQueryParams(['per_page' => 10000])
        ->test(DataTable::class, [
            'columns' => memberColumns(),
            'model' => Member::class,
        ])
        ->assertSet('perPage', 12); // Falls back to default
});

it('throws exception for invalid scope name', function () {
    Member::factory()->create();

    expect(fn () => Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'scopes' => ['nonExistentScope' => true],
    ]))->toThrow(\Illuminate\View\ViewException::class);
});

it('falls back to asc for invalid sort direction', function () {
    Member::factory()->create(['name' => 'Alpha']);
    Member::factory()->create(['name' => 'Zebra']);

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('sortBy', 'name')
        ->set('sortDirection', 'invalid')
        ->assertStatus(200);
});

it('applies withCounts parameter', function () {
    $member = Member::factory()->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'withCounts' => ['emails', 'phones'],
    ])
        ->assertStatus(200)
        ->assertViewHas('items', fn ($items) => $items->first()->emails_count !== null);
});

it('applies scope with value parameter', function () {
    Member::factory()->create(['membership_type' => MembershipType::Contact]);
    Member::factory()->create(['membership_type' => MembershipType::Organisation]);

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'scopes' => ['ofType' => MembershipType::Contact],
    ])
        ->assertViewHas('items', fn ($items) => $items->count() === 1);
});

it('applies scope with boolean true parameter', function () {
    Member::factory()->create(['is_active' => true]);
    Member::factory()->create(['is_active' => false]);
    $activeCount = Member::query()->active()->count();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'scopes' => ['active' => true],
    ])
        ->assertViewHas('items', fn ($items) => $items->count() === $activeCount);
});

it('selects all page IDs when selectAll is toggled and renders', function () {
    Member::factory()->count(3)->create();
    $total = Member::count();

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ]);

    $component->call('toggleSelectAll');
    $selected = $component->get('selected');

    expect($selected)->toHaveCount($total);
});

it('removes filter when empty value is applied', function () {
    Member::factory()->count(3)->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('applyFilter', 'membership_type', 'contact')
        ->assertSet('filters', ['membership_type' => 'contact'])
        ->call('applyFilter', 'membership_type', '')
        ->assertSet('filters', []);
});

it('shift-select falls back to toggle when lastSelectedId not in pageIds', function () {
    $members = Member::factory()->count(3)->create();
    $ids = $members->sortBy('id')->pluck('id')->values()->all();

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ]);

    // Select first member
    $component->call('toggleSelected', $ids[0]);

    // Shift-click with pageIds that don't contain lastSelectedId
    $component->call('shiftSelect', $ids[2], [$ids[1], $ids[2]]);
    $selected = $component->get('selected');

    // Should fall back to toggleSelected since lastSelectedId ($ids[0]) is not in pageIds
    expect($selected)->toContain($ids[2]);
});

it('filters by text column using ilike', function () {
    if (config('database.default') === 'sqlite') {
        $this->markTestSkipped('Text filter uses PostgreSQL ilike operator');
    }

    Member::factory()->create(['name' => 'Acme Events']);
    Member::factory()->create(['name' => 'Beta Sound']);

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('applyFilter', 'name', 'acme')
        ->assertViewHas('items', fn ($items) => $items->count() === 1 && $items->first()->name === 'Acme Events');
});

it('responds to multiple configured refresh events', function () {
    $member = Member::factory()->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'refreshEvents' => ['member-created', 'member-deleted'],
    ])
        ->call('toggleSelected', $member->id)
        ->assertSet('selected', [$member->id])
        ->dispatch('member-created')
        ->assertSet('selected', [])
        ->assertSet('selectAll', false);
});

it('refresh method resets selection state', function () {
    $member = Member::factory()->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('toggleSelected', $member->id)
        ->assertSet('selected', [$member->id])
        ->call('refresh')
        ->assertSet('selected', [])
        ->assertSet('selectAll', false)
        ->assertSet('lastSelectedId', null);
});

it('toggleSelectAll deselects when toggled twice', function () {
    Member::factory()->count(3)->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('toggleSelectAll')
        ->assertSet('selectAll', true)
        ->call('toggleSelectAll')
        ->assertSet('selectAll', false)
        ->assertSet('selected', []);
});

it('resets page when updatedFilters is called', function () {
    Member::factory()->count(30)->create();

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'searchable' => ['name'],
        'perPage' => 12,
    ]);

    // Apply a select filter (works on SQLite) to trigger updatedFilters
    $component->call('applyFilter', 'membership_type', 'contact');

    // The page should reset — we just verify no error
    $component->assertStatus(200);
});

<?php

use App\Enums\MembershipType;
use App\Livewire\Components\DataTable;
use App\Models\Member;
use App\Models\User;
use Livewire\Livewire;
use Tests\Concerns\UsesPostgres;

use function Pest\Laravel\actingAs;

uses(UsesPostgres::class);

/**
 * @return array<int, array<string, mixed>>
 */
function pgMemberColumns(): array
{
    return [
        ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text'],
        ['key' => 'membership_type', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select'],
    ];
}

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    actingAs(User::factory()->create());
});

it('searches searchable columns case-insensitively via ilike', function () {
    // Exercises the global-search where-group (lines 380-387) which builds an
    // `ilike` predicate — PostgreSQL only.
    Member::factory()->create(['name' => 'Acme Events']);
    Member::factory()->create(['name' => 'Beta Sound']);

    Livewire::test(DataTable::class, [
        'columns' => pgMemberColumns(),
        'model' => Member::class,
        'searchable' => ['name'],
    ])
        ->set('search', 'acme')
        ->assertViewHas('items', fn ($items) => $items->count() === 1 && $items->first()->name === 'Acme Events');
})->group('pgsql');

it('filters a text column case-insensitively via ilike', function () {
    // Exercises the text-filter branch (line 404), also `ilike`.
    Member::factory()->create(['name' => 'Northern Lights', 'membership_type' => MembershipType::Organisation]);
    Member::factory()->create(['name' => 'Southern Cross', 'membership_type' => MembershipType::Organisation]);

    Livewire::test(DataTable::class, [
        'columns' => pgMemberColumns(),
        'model' => Member::class,
    ])
        ->call('applyFilter', 'name', 'northern')
        ->assertViewHas('items', fn ($items) => $items->count() === 1 && $items->first()->name === 'Northern Lights');
})->group('pgsql');

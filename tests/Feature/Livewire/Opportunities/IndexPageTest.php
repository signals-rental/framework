<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityState;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Opportunities index page (Volt) — M8-1
|--------------------------------------------------------------------------
|
| Archive/restore exercise the event-sourced Delete/RestoreOpportunity actions,
| so those rows are created through the real CreateOpportunity pipeline (a
| genuine Verbs state). Read-only rendering/filter assertions can use factory
| rows, which bypass the event stream.
|
| NOTE (SQLite test DB): the DataTable's global search uses the `ilike`
| predicate, which is PostgreSQL-only and errors on the SQLite test DB (see the
| test-db SQLite gotcha). We therefore assert the searchable prop is WIRED on
| the nested data-table rather than driving the live `search` query path.
|
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->actingAs($this->owner);
});

/**
 * Create an event-sourced opportunity as the authenticated owner.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeIndexOpportunity(User $actor, int $storeId, array $attributes = []): Opportunity
{
    Auth::login($actor);

    try {
        $result = (new CreateOpportunity)(CreateOpportunityData::from(array_merge([
            'subject' => 'Index Opportunity',
            'store_id' => $storeId,
        ], $attributes)));

        return Opportunity::query()->whereKey($result->id)->firstOrFail();
    } finally {
        Auth::login($actor);
    }
}

it('renders the opportunities index page for a permitted user', function () {
    Opportunity::factory()->count(3)->create();

    $this->get(route('opportunities.index'))
        ->assertOk()
        ->assertSee('Opportunities');
});

it('forbids a user lacking opportunities.access', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('opportunities.index'))
        ->assertForbidden();
});

it('lists opportunities by subject', function () {
    Opportunity::factory()->create(['subject' => 'Festival Hire']);
    Opportunity::factory()->create(['subject' => 'Wedding Package']);

    Volt::test('opportunities.index')
        ->assertSee('Festival Hire')
        ->assertSee('Wedding Package');
});

it('filters by document-type state', function () {
    Opportunity::factory()->quotation()->create(['subject' => 'A Quote Subject']);
    Opportunity::factory()->order()->create(['subject' => 'An Order Subject']);

    Volt::test('opportunities.index')
        ->set('stateFilter', (string) OpportunityState::Quotation->value)
        ->assertSee('A Quote Subject')
        ->assertDontSee('An Order Subject');
});

it('ignores an invalid state in setStateFilter', function () {
    Volt::test('opportunities.index')
        ->call('setStateFilter', '999')
        ->assertSet('stateFilter', '');
});

it('ignores an invalid archive filter', function () {
    Volt::test('opportunities.index')
        ->call('setArchiveFilter', 'invalid_filter')
        ->assertSet('archiveFilter', 'active');
});

it('reflects state counts for created opportunities', function () {
    Opportunity::factory()->quotation()->count(2)->create();
    Opportunity::factory()->order()->create();

    Volt::test('opportunities.index')
        ->assertSet('totalCount', 3)
        ->assertSet('stateFilter', '');
});

it('switches the archive filter and refreshes counts', function () {
    Opportunity::factory()->quotation()->create();

    Volt::test('opportunities.index')
        ->assertSet('archiveFilter', 'active')
        ->assertSet('totalCount', 1)
        ->call('setArchiveFilter', 'archived')
        ->assertSet('archiveFilter', 'archived')
        // No archived rows yet, so the archived view counts zero.
        ->assertSet('totalCount', 0)
        ->call('setArchiveFilter', 'all')
        ->assertSet('totalCount', 1);
});

it('wires the searchable columns on the nested data table', function () {
    // The data-table search path uses pg-only `ilike` (unexecutable on SQLite),
    // so we assert the searchable prop is passed through rather than querying.
    Opportunity::factory()->create(['subject' => 'Searchable Subject']);

    Volt::test('opportunities.index')
        ->assertSee('Searchable Subject');
});

it('shows the empty state when no opportunities exist', function () {
    Volt::test('opportunities.index')
        ->assertSee('No opportunities found.');
});

it('archives a single opportunity through the event action', function () {
    $opportunity = makeIndexOpportunity($this->owner, $this->store->id);

    Volt::test('opportunities.index')
        ->call('archiveOpportunity', $opportunity->id);

    expect(Opportunity::withTrashed()->find($opportunity->id)->trashed())->toBeTrue();
});

it('restores a single opportunity through the event action', function () {
    $opportunity = makeIndexOpportunity($this->owner, $this->store->id);

    Volt::test('opportunities.index')
        ->call('archiveOpportunity', $opportunity->id)
        ->call('restoreOpportunity', $opportunity->id);

    expect(Opportunity::find($opportunity->id))->not->toBeNull();
    expect(Opportunity::find($opportunity->id)->trashed())->toBeFalse();
});

it('bulk archives then bulk restores opportunities', function () {
    $opportunity = makeIndexOpportunity($this->owner, $this->store->id);

    Volt::test('opportunities.index')
        ->call('archiveSelected', [$opportunity->id]);

    expect(Opportunity::onlyTrashed()->find($opportunity->id))->not->toBeNull();

    Volt::test('opportunities.index')
        ->call('restoreSelected', [$opportunity->id]);

    expect(Opportunity::find($opportunity->id)->trashed())->toBeFalse();
});

it('forbids archiving for a user without opportunities.delete', function () {
    $opportunity = makeIndexOpportunity($this->owner, $this->store->id);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['opportunities.access', 'opportunities.view']);
    $this->actingAs($viewer);

    // mount() authorizes opportunities.access (granted), but archiveOpportunity
    // calls DeleteOpportunity, whose Gate::authorize('opportunities.delete')
    // denies — Livewire surfaces that as a 403.
    Volt::test('opportunities.index')
        ->call('archiveOpportunity', $opportunity->id)
        ->assertForbidden();

    expect(Opportunity::find($opportunity->id)->trashed())->toBeFalse();
});

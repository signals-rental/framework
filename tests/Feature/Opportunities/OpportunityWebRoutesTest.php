<?php

use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

it('registers the named opportunity web routes', function () {
    expect(Route::has('opportunities.index'))->toBeTrue()
        ->and(Route::has('opportunities.create'))->toBeTrue()
        ->and(Route::has('opportunities.show'))->toBeTrue()
        ->and(Route::has('opportunities.edit'))->toBeTrue();
});

it('resolves route() for a show url without erroring', function () {
    $opportunity = Opportunity::factory()->create();

    expect(route('opportunities.show', $opportunity->id))
        ->toContain('/opportunities/'.$opportunity->id);
});

it('renders the gated index placeholder for an authorised user', function () {
    $this->actingAs($this->owner);

    Volt::test('opportunities.index')
        ->assertOk()
        ->assertSee('Opportunities');
});

it('renders the show page for an authorised user', function () {
    // Use a DB-fresh model, exactly as route-model binding hands the page in
    // production: the integer money totals carry their DB default of 0 (a raw
    // factory instance leaves the default-valued columns null in memory).
    $opportunity = Opportunity::factory()->create(['subject' => 'Demo Opportunity'])->fresh();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('Demo Opportunity')
        ->assertSee('Charge Total');
});

it('renders the create form for an authorised user', function () {
    $this->actingAs($this->owner);

    Volt::test('opportunities.form')
        ->assertOk()
        ->assertSee('New Opportunity')
        ->assertSee('Create Opportunity');
});

it('forbids the index placeholder for a user without opportunities.access', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    Volt::test('opportunities.index')->assertForbidden();
});

it('forbids the show placeholder for a user without opportunities.view', function () {
    $opportunity = Opportunity::factory()->create();

    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])->assertForbidden();
});

it('shows an archived (soft-deleted) opportunity instead of 404ing', function () {
    // Regression: the Show route binding excluded trashed rows, so an archived
    // opportunity 404'd. The binding now resolves ->withTrashed(); the HTTP request
    // (which runs route-model binding, unlike Volt::test) must return 200, surface
    // the archived banner, and offer Restore.
    $opportunity = Opportunity::factory()->create(['subject' => 'Archived Opportunity']);
    $opportunity->delete();

    expect($opportunity->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->owner);

    $this->get(route('opportunities.show', $opportunity->id))
        ->assertOk()
        ->assertSee('Archived Opportunity')
        ->assertSee('This opportunity is archived')
        ->assertSee('Restore');
});

it('keeps an archived opportunity read-only — no transition actions, only Restore', function () {
    $opportunity = Opportunity::factory()->create();
    $opportunity->delete();

    $this->actingAs($this->owner);

    // While archived the transition actions + status picker are suppressed; the
    // only mutating affordance is Restore (gated on opportunities.delete).
    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->assertOk()
        ->assertViewHas('isArchived', true)
        ->assertViewHas('canRestore', true)
        ->assertViewHas('availableActions', [])
        ->assertViewHas('canChangeStatus', false);
});

it('renders an archived opportunity sub-tab (versions) instead of 404ing', function () {
    $opportunity = Opportunity::factory()->create();
    $opportunity->delete();

    $this->actingAs($this->owner);

    $this->get(route('opportunities.versions', $opportunity->id))->assertOk();
});

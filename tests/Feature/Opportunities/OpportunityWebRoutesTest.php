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

it('renders the gated show placeholder for an authorised user', function () {
    $opportunity = Opportunity::factory()->create(['subject' => 'Demo Opportunity']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('Demo Opportunity');
});

it('renders the create form placeholder for an authorised user', function () {
    $this->actingAs($this->owner);

    Volt::test('opportunities.form')
        ->assertOk()
        ->assertSee('New opportunity');
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

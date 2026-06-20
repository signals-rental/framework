<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Dashboard "New Opportunity" quick action (M8-6)
|--------------------------------------------------------------------------
|
| The Quick Actions block exposes a "New Opportunity" shortcut to the create
| form, gated on `opportunities.create`. Users without that permission do not
| see it.
|
*/

it('shows the New Opportunity quick action to a user with opportunities.create', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('opportunities.create');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('New Opportunity')
        ->assertSee(route('opportunities.create'), false);
});

it('hides the New Opportunity quick action from a user without opportunities.create', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('New Opportunity')
        ->assertDontSee(route('opportunities.create'), false);
});

it('shows the opportunity pipeline widget to a user with opportunities.access', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('opportunities.access');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Opportunity Pipeline');
});

it('hides the opportunity pipeline widget from a user without opportunities.access', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Opportunity Pipeline');
});

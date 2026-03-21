<?php

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('renders the activities index page', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get('/activities')
        ->assertOk()
        ->assertSee('Activities');
});

it('displays activities in the data table', function () {
    $user = User::factory()->owner()->create();
    Activity::factory()->create(['subject' => 'Test Activity']);

    $this->actingAs($user)
        ->get('/activities')
        ->assertOk()
        ->assertSee('Test Activity');
});

it('shows the new activity button', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get('/activities')
        ->assertOk()
        ->assertSee('New Activity');
});

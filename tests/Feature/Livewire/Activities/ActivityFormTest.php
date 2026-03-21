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

it('renders the create activity form', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get('/activities/create')
        ->assertOk()
        ->assertSee('Create Activity');
});

it('renders the edit activity form', function () {
    $user = User::factory()->owner()->create();
    $activity = Activity::factory()->create(['subject' => 'Edit Me']);

    $this->actingAs($user)
        ->get("/activities/{$activity->id}/edit")
        ->assertOk()
        ->assertSee('Edit Me');
});

<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('renders the permissions reference page', function () {
    $this->get(route('admin.settings.permissions'))
        ->assertOk()
        ->assertSee('Permissions Reference');
});

it('shows permission groups', function () {
    $this->get(route('admin.settings.permissions'))
        ->assertSee('Settings')
        ->assertSee('Users')
        ->assertSee('Opportunities');
});

it('shows permission labels', function () {
    $this->get(route('admin.settings.permissions'))
        ->assertSee('View Settings')
        ->assertSee('Create Members')
        ->assertSee('Manage Webhooks');
});

it('shows role names in headers', function () {
    $this->get(route('admin.settings.permissions'))
        ->assertSee('Owner')
        ->assertSee('Admin')
        ->assertSee('Viewer');
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.permissions'))
        ->assertForbidden();
});

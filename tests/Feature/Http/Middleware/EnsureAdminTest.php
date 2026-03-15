<?php

use App\Models\User;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('allows admin users to access admin routes', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/admin/settings/company')
        ->assertOk();
});

it('allows owner users to access admin routes', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get('/admin/settings/company')
        ->assertOk();
});

it('returns 403 for non-admin users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/settings/company')
        ->assertForbidden();
});

it('redirects unauthenticated users to login', function () {
    $this->get('/admin/settings/company')
        ->assertRedirect(route('login'));
});

it('renders admin landing page', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});

it('redirects /admin/settings to /admin', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/admin/settings')
        ->assertRedirect('/admin');
});

it('allows users with Admin Spatie role to access admin routes', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user)
        ->get('/admin/settings/company')
        ->assertOk();
});

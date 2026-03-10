<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('Admin routes are accessible', function () {
    it('renders company settings', function () {
        $this->get(route('admin.settings.company'))->assertOk();
    });

    it('renders stores', function () {
        $this->get(route('admin.settings.stores'))->assertOk();
    });

    it('renders branding', function () {
        $this->get(route('admin.settings.branding'))->assertOk();
    });

    it('renders modules', function () {
        $this->get(route('admin.settings.modules'))->assertOk();
    });

    it('renders users', function () {
        $this->get(route('admin.settings.users'))->assertOk();
    });

    it('renders roles', function () {
        $this->get(route('admin.settings.roles'))->assertOk();
    });

    it('renders create role page', function () {
        $this->get(route('admin.settings.roles.create'))->assertOk();
    });

    it('renders edit user page', function () {
        $this->admin->givePermissionTo('users.edit');
        $user = User::factory()->create();
        $this->get(route('admin.settings.users.edit', $user))->assertOk();
    });

    it('renders permissions', function () {
        $this->get(route('admin.settings.permissions'))->assertOk();
    });

    it('renders security', function () {
        $this->get(route('admin.settings.security'))->assertOk();
    });

    it('renders email', function () {
        $this->get(route('admin.settings.email'))->assertOk();
    });
});

describe('Non-admin access is denied', function () {
    it('returns 403 for all admin routes', function () {
        $regularUser = User::factory()->create();
        $this->actingAs($regularUser);

        $routes = [
            'admin.settings.company',
            'admin.settings.stores',
            'admin.settings.branding',
            'admin.settings.modules',
            'admin.settings.users',
            'admin.settings.roles',
            'admin.settings.permissions',
            'admin.settings.security',
            'admin.settings.email',
        ];

        foreach ($routes as $routeName) {
            $this->get(route($routeName))->assertForbidden();
        }
    });
});

describe('Sidebar navigation', function () {
    it('shows all nav group labels', function () {
        $this->get(route('admin.settings.company'))
            ->assertSee('Account')
            ->assertSee('Users & Security', false)
            ->assertSee('Preferences');
    });

    it('shows all navigation links', function () {
        $this->get(route('admin.settings.company'))
            ->assertSee('Company Details')
            ->assertSee('Stores')
            ->assertSee('Branding')
            ->assertSee('Modules')
            ->assertSee('Users')
            ->assertSee('Roles')
            ->assertSee('Permissions Reference')
            ->assertSee('Security')
            ->assertSee('Email');
    });
});

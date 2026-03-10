<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('Permissions', function () {
    it('seeds all expected permissions', function () {
        $expected = array_keys(PermissionSeeder::permissions());

        foreach ($expected as $key) {
            expect(Permission::findByName($key, 'web'))->not->toBeNull();
        }
    });

    it('creates the correct number of permissions', function () {
        $expected = count(PermissionSeeder::permissions());

        expect(Permission::count())->toBe($expected);
    });
});

describe('Roles', function () {
    it('creates all four default roles', function () {
        expect(Role::count())->toBe(4);
        expect(Role::findByName('Admin', 'web'))->not->toBeNull();
        expect(Role::findByName('Manager', 'web'))->not->toBeNull();
        expect(Role::findByName('Operator', 'web'))->not->toBeNull();
        expect(Role::findByName('Viewer', 'web'))->not->toBeNull();
    });

    it('marks all default roles as system roles', function () {
        Role::all()->each(function ($role) {
            expect((bool) $role->getAttribute('is_system'))->toBeTrue();
        });
    });

    it('gives Admin role all permissions', function () {
        $admin = Role::findByName('Admin', 'web');
        $allPermissions = array_keys(PermissionSeeder::permissions());

        expect($admin->permissions->pluck('name')->sort()->values()->all())
            ->toBe(collect($allPermissions)->sort()->values()->all());
    });

    it('gives Viewer role only view permissions', function () {
        $viewer = Role::findByName('Viewer', 'web');
        $viewerPermissions = $viewer->permissions->pluck('name')->all();

        foreach ($viewerPermissions as $permission) {
            expect($permission)->toEndWith('.view');
        }
    });

    it('gives Manager role no settings/users/roles permissions', function () {
        $manager = Role::findByName('Manager', 'web');
        $managerPermissions = $manager->permissions->pluck('name')->all();

        foreach ($managerPermissions as $permission) {
            expect($permission)->not->toStartWith('settings.');
            expect($permission)->not->toStartWith('users.');
            expect($permission)->not->toStartWith('roles.');
        }
    });
});

describe('Owner bypass', function () {
    it('grants owner implicit access to all gates', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner);

        expect(Gate::allows('settings.manage'))->toBeTrue();
        expect(Gate::allows('users.edit'))->toBeTrue();
        expect(Gate::allows('nonexistent.permission'))->toBeTrue();
    });

    it('does not grant non-owner implicit access', function () {
        $user = User::factory()->create();

        expect($user->isOwner())->toBeFalse();
    });
});

describe('User model extensions', function () {
    it('has is_active default to true', function () {
        $user = User::factory()->create();

        expect($user->isActive())->toBeTrue();
    });

    it('supports the deactivated factory state', function () {
        $user = User::factory()->deactivated()->create();

        expect($user->isActive())->toBeFalse();
        expect($user->deactivated_at)->not->toBeNull();
    });

    it('supports the invited factory state', function () {
        $user = User::factory()->invited()->create();

        expect($user->invited_at)->not->toBeNull();
        expect($user->invitation_accepted_at)->toBeNull();
        expect($user->password)->toBeNull();
    });

    it('hasAdminAccess returns true for owners', function () {
        $user = User::factory()->owner()->create();

        expect($user->hasAdminAccess())->toBeTrue();
    });

    it('hasAdminAccess returns true for admins', function () {
        $user = User::factory()->admin()->create();

        expect($user->hasAdminAccess())->toBeTrue();
    });

    it('hasAdminAccess returns true for users with Admin role', function () {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        expect($user->fresh()->hasAdminAccess())->toBeTrue();
    });

    it('hasAdminAccess returns false for regular users', function () {
        $user = User::factory()->create();

        expect($user->hasAdminAccess())->toBeFalse();
    });
});

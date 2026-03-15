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
    it('creates all five default roles', function () {
        expect(Role::count())->toBe(5);
        expect(Role::findByName('Admin', 'web'))->not->toBeNull();
        expect(Role::findByName('Operations Manager', 'web'))->not->toBeNull();
        expect(Role::findByName('Sales', 'web'))->not->toBeNull();
        expect(Role::findByName('Warehouse', 'web'))->not->toBeNull();
        expect(Role::findByName('Read Only', 'web'))->not->toBeNull();
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

    it('gives Admin role cost visibility', function () {
        $admin = Role::findByName('Admin', 'web');

        expect((bool) $admin->getAttribute('cost_visibility'))->toBeTrue();
    });

    it('gives Operations Manager role cost visibility', function () {
        $opsManager = Role::findByName('Operations Manager', 'web');

        expect((bool) $opsManager->getAttribute('cost_visibility'))->toBeTrue();
    });

    it('denies Sales role cost visibility', function () {
        $sales = Role::findByName('Sales', 'web');

        expect((bool) $sales->getAttribute('cost_visibility'))->toBeFalse();
    });

    it('denies Warehouse role cost visibility', function () {
        $warehouse = Role::findByName('Warehouse', 'web');

        expect((bool) $warehouse->getAttribute('cost_visibility'))->toBeFalse();
    });

    it('gives Read Only role only view and access permissions', function () {
        $readOnly = Role::findByName('Read Only', 'web');
        $readOnlyPermissions = $readOnly->permissions->pluck('name')->all();

        foreach ($readOnlyPermissions as $permission) {
            expect($permission)->toMatch('/\.(view|access)$/');
        }
    });

    it('gives Operations Manager role no settings/users/roles permissions', function () {
        $opsManager = Role::findByName('Operations Manager', 'web');
        $opsPermissions = $opsManager->permissions->pluck('name')->all();

        foreach ($opsPermissions as $permission) {
            expect($permission)->not->toStartWith('settings.');
            expect($permission)->not->toStartWith('users.');
            expect($permission)->not->toStartWith('roles.');
        }
    });

    it('gives Sales role opportunity and invoice permissions', function () {
        $sales = Role::findByName('Sales', 'web');
        $salesPermissions = $sales->permissions->pluck('name')->all();

        expect($salesPermissions)->toContain('opportunities.create');
        expect($salesPermissions)->toContain('invoices.create');
        expect($salesPermissions)->toContain('members.view');
        expect($salesPermissions)->not->toContain('stock.adjust');
        expect($salesPermissions)->not->toContain('settings.manage');
    });

    it('gives Warehouse role stock and product permissions', function () {
        $warehouse = Role::findByName('Warehouse', 'web');
        $warehousePermissions = $warehouse->permissions->pluck('name')->all();

        expect($warehousePermissions)->toContain('stock.view');
        expect($warehousePermissions)->toContain('stock.adjust');
        expect($warehousePermissions)->toContain('products.view');
        expect($warehousePermissions)->not->toContain('opportunities.create');
        expect($warehousePermissions)->not->toContain('invoices.create');
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

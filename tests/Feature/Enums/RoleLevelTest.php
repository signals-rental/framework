<?php

use App\Enums\RoleLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('fromRoleName', function () {
    it('returns Admin for Admin role', function () {
        expect(RoleLevel::fromRoleName('Admin'))->toBe(RoleLevel::Admin);
    });

    it('returns OperationsManager for Operations Manager role', function () {
        expect(RoleLevel::fromRoleName('Operations Manager'))->toBe(RoleLevel::OperationsManager);
    });

    it('returns Sales for Sales role', function () {
        expect(RoleLevel::fromRoleName('Sales'))->toBe(RoleLevel::Sales);
    });

    it('returns Warehouse for Warehouse role', function () {
        expect(RoleLevel::fromRoleName('Warehouse'))->toBe(RoleLevel::Warehouse);
    });

    it('returns ReadOnly for Read Only role', function () {
        expect(RoleLevel::fromRoleName('Read Only'))->toBe(RoleLevel::ReadOnly);
    });

    it('returns null for unknown role name', function () {
        expect(RoleLevel::fromRoleName('Custom Role'))->toBeNull();
    });
});

describe('levelFor', function () {
    it('returns 80 for Admin', function () {
        expect(RoleLevel::levelFor('Admin'))->toBe(80);
    });

    it('returns 60 for Operations Manager', function () {
        expect(RoleLevel::levelFor('Operations Manager'))->toBe(60);
    });

    it('returns 40 for Sales', function () {
        expect(RoleLevel::levelFor('Sales'))->toBe(40);
    });

    it('returns 35 for Warehouse', function () {
        expect(RoleLevel::levelFor('Warehouse'))->toBe(35);
    });

    it('returns 20 for Read Only', function () {
        expect(RoleLevel::levelFor('Read Only'))->toBe(20);
    });

    it('returns 0 for unknown role', function () {
        expect(RoleLevel::levelFor('Unknown Role'))->toBe(0);
    });
});

describe('forUser', function () {
    it('returns 100 for owner user', function () {
        $user = User::factory()->owner()->create();

        expect(RoleLevel::forUser($user))->toBe(100);
    });

    it('returns role level for non-owner with a role', function () {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        expect(RoleLevel::forUser($user))->toBe(80);
    });

    it('returns highest role level when user has multiple roles', function () {
        $user = User::factory()->create();
        $user->assignRole('Read Only');
        $user->assignRole('Sales');

        expect(RoleLevel::forUser($user))->toBe(40);
    });

    it('returns 0 for user with no roles', function () {
        $user = User::factory()->create();

        expect(RoleLevel::forUser($user))->toBe(0);
    });
});

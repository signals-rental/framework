<?php

use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

/**
 * Guards that the management-tier roles retain the custom-fields permissions.
 * These were previously granted only incidentally via prefix-based filters; this
 * test makes the contract explicit so a future refactor of RoleSeeder cannot
 * silently lock non-owner admins out of custom field management.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('grants both custom-fields permissions to the Admin role', function () {
    $admin = Role::query()->where('name', 'Admin')->first();

    expect($admin)->not->toBeNull();
    expect($admin->hasPermissionTo('custom-fields.view'))->toBeTrue();
    expect($admin->hasPermissionTo('custom-fields.manage'))->toBeTrue();
});

it('grants both custom-fields permissions to the Operations Manager role', function () {
    $opsManager = Role::query()->where('name', 'Operations Manager')->first();

    expect($opsManager)->not->toBeNull();
    expect($opsManager->hasPermissionTo('custom-fields.view'))->toBeTrue();
    expect($opsManager->hasPermissionTo('custom-fields.manage'))->toBeTrue();
});

it('grants view-only custom-fields permission to the Read Only role', function () {
    $readOnly = Role::query()->where('name', 'Read Only')->first();

    expect($readOnly)->not->toBeNull();
    expect($readOnly->hasPermissionTo('custom-fields.view'))->toBeTrue();
    expect($readOnly->hasPermissionTo('custom-fields.manage'))->toBeFalse();
});

it('is idempotent across repeated seeding', function () {
    $this->seed(RoleSeeder::class);

    $admin = Role::query()->where('name', 'Admin')->first();

    expect(Role::query()->where('name', 'Admin')->count())->toBe(1);
    expect($admin->hasPermissionTo('custom-fields.manage'))->toBeTrue();
});

<?php

use App\Actions\Admin\DeleteRole;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('deletes a non-system role with no users', function () {
    /** @var Role $role */
    $role = Role::create([
        'name' => 'Deletable Role',
        'guard_name' => 'web',
        'is_system' => false,
    ]);

    (new DeleteRole)($role);

    expect(Role::where('name', 'Deletable Role')->exists())->toBeFalse();
});

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    /** @var Role $role */
    $role = Role::create([
        'name' => 'Some Role',
        'guard_name' => 'web',
        'is_system' => false,
    ]);

    (new DeleteRole)($role);
})->throws(AuthorizationException::class);

it('prevents deleting a system role', function () {
    $role = Role::where('is_system', true)->first();

    (new DeleteRole)($role);
})->throws(ValidationException::class);

it('prevents deleting a role with assigned users', function () {
    /** @var Role $role */
    $role = Role::create([
        'name' => 'Assigned Role',
        'guard_name' => 'web',
        'is_system' => false,
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    (new DeleteRole)($role);
})->throws(ValidationException::class);

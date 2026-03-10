<?php

use App\Actions\Admin\CreateRole;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a role with name and permissions', function () {
    $role = (new CreateRole)([
        'name' => 'Custom Role',
        'description' => 'A custom role for testing',
        'permissions' => ['members.view', 'members.create'],
    ]);

    expect($role)->toBeInstanceOf(Role::class);
    expect($role->name)->toBe('Custom Role');
    expect($role->getAttribute('description'))->toBe('A custom role for testing');
    expect($role->getAttribute('is_system'))->toBeFalse();
    expect($role->permissions->pluck('name')->toArray())
        ->toContain('members.view')
        ->toContain('members.create');
});

it('sets sort_order automatically', function () {
    $maxBefore = Role::max('sort_order');

    $role = (new CreateRole)([
        'name' => 'Sorted Role',
        'permissions' => [],
    ]);

    expect($role->getAttribute('sort_order'))->toBe($maxBefore + 1);
});

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    (new CreateRole)([
        'name' => 'Unauthorized Role',
        'permissions' => [],
    ]);
})->throws(AuthorizationException::class);

it('creates a role without permissions', function () {
    $role = (new CreateRole)([
        'name' => 'Empty Role',
        'permissions' => [],
    ]);

    expect($role->name)->toBe('Empty Role');
    expect($role->permissions)->toHaveCount(0);
});

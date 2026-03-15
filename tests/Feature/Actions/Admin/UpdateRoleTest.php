<?php

use App\Actions\Admin\UpdateRole;
use App\Events\AuditableEvent;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('updates role name and description', function () {
    /** @var Role $role */
    $role = Role::create([
        'name' => 'Original Name',
        'guard_name' => 'web',
        'description' => 'Original description',
        'is_system' => false,
        'sort_order' => 100,
    ]);

    $updated = (new UpdateRole)($role, [
        'name' => 'Updated Name',
        'description' => 'Updated description',
    ]);

    expect($updated->name)->toBe('Updated Name');
    expect($updated->getAttribute('description'))->toBe('Updated description');
});

it('prevents renaming system roles', function () {
    /** @var Role $systemRole */
    $systemRole = Role::query()->where('is_system', true)->first();

    (new UpdateRole)($systemRole, [
        'name' => 'Renamed System Role',
    ]);
})->throws(ValidationException::class, 'System roles cannot be renamed.');

it('syncs permissions on update', function () {
    /** @var Role $role */
    $role = Role::create([
        'name' => 'Perm Role',
        'guard_name' => 'web',
        'is_system' => false,
        'sort_order' => 100,
    ]);
    $role->syncPermissions(['members.view']);

    $updated = (new UpdateRole)($role, [
        'permissions' => ['members.create', 'members.edit'],
    ]);

    $permissionNames = $updated->permissions->pluck('name')->toArray();
    expect($permissionNames)->toContain('members.create');
    expect($permissionNames)->toContain('members.edit');
    expect($permissionNames)->not->toContain('members.view');
});

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    /** @var Role $role */
    $role = Role::query()->where('is_system', false)->first()
        ?? Role::create(['name' => 'Test Role', 'guard_name' => 'web', 'is_system' => false, 'sort_order' => 100]);

    (new UpdateRole)($role, [
        'name' => 'Hacked Name',
    ]);
})->throws(AuthorizationException::class);

it('rejects unregistered permissions on update', function () {
    /** @var Role $role */
    $role = Role::create([
        'name' => 'Bad Update Role',
        'guard_name' => 'web',
        'is_system' => false,
        'sort_order' => 100,
    ]);

    (new UpdateRole)($role, [
        'permissions' => ['members.view', 'nonexistent.permission'],
    ]);
})->throws(ValidationException::class, 'not registered');

it('dispatches an auditable event on update', function () {
    Event::fake([AuditableEvent::class]);

    /** @var Role $role */
    $role = Role::create([
        'name' => 'Audit Update Role',
        'guard_name' => 'web',
        'is_system' => false,
        'sort_order' => 100,
    ]);

    (new UpdateRole)($role, [
        'name' => 'Renamed Audit Role',
        'permissions' => ['members.view'],
    ]);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'updated'
            && $event->oldValues['name'] === 'Audit Update Role'
            && $event->newValues['name'] === 'Renamed Audit Role';
    });
});

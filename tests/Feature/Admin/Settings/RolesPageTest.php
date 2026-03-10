<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('renders the roles page', function () {
    $this->get(route('admin.settings.roles'))
        ->assertOk()
        ->assertSee('Roles');
});

it('lists all default roles', function () {
    $this->get(route('admin.settings.roles'))
        ->assertSee('Admin')
        ->assertSee('Manager')
        ->assertSee('Operator')
        ->assertSee('Viewer');
});

it('renders the create role page', function () {
    $this->get(route('admin.settings.roles.create'))
        ->assertOk()
        ->assertSee('Create Role');
});

it('renders the edit role page', function () {
    $role = Role::create(['name' => 'Test Role', 'guard_name' => 'web']);

    $this->get(route('admin.settings.roles.edit', $role))
        ->assertOk()
        ->assertSee('Edit Role');
});

it('creates a custom role', function () {
    Volt::test('admin.settings.role-form')
        ->set('roleName', 'Custom Role')
        ->set('roleDescription', 'A custom role')
        ->set('selectedPermissions', ['members.view', 'members.create'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.settings.roles'));

    $role = Role::findByName('Custom Role', 'web');
    expect($role)->not->toBeNull();
    expect($role->hasPermissionTo('members.view'))->toBeTrue();
    expect($role->hasPermissionTo('members.create'))->toBeTrue();
    expect((bool) $role->getAttribute('is_system'))->toBeFalse();
});

it('updates a role', function () {
    $role = Role::create(['name' => 'Test Role', 'guard_name' => 'web']);

    Volt::test('admin.settings.role-form', ['role' => $role])
        ->set('roleName', 'Updated Role')
        ->set('roleDescription', 'Updated description')
        ->set('selectedPermissions', ['products.view'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.settings.roles'));

    $role->refresh();
    expect($role->name)->toBe('Updated Role');
    expect($role->hasPermissionTo('products.view'))->toBeTrue();
});

it('prevents renaming system roles', function () {
    $admin = Role::findByName('Admin', 'web');

    Volt::test('admin.settings.role-form', ['role' => $admin])
        ->set('roleName', 'Super Admin')
        ->call('save')
        ->assertHasErrors();
});

it('deletes a custom role', function () {
    $role = Role::create(['name' => 'Deletable', 'guard_name' => 'web']);

    Volt::test('admin.settings.roles')
        ->call('deleteRole', $role->id)
        ->assertDispatched('role-deleted');

    expect(Role::where('name', 'Deletable')->exists())->toBeFalse();
});

it('prevents deleting system roles', function () {
    $admin = Role::findByName('Admin', 'web');

    Volt::test('admin.settings.roles')
        ->call('deleteRole', $admin->id)
        ->assertHasErrors();
});

it('prevents deleting roles with assigned users', function () {
    $role = Role::create(['name' => 'Used Role', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('Used Role');

    Volt::test('admin.settings.roles')
        ->call('deleteRole', $role->id)
        ->assertHasErrors();
});

it('loads users assigned to a role', function () {
    $role = Role::create(['name' => 'Test Role', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('Test Role');

    $component = Volt::test('admin.settings.role-form', ['role' => $role]);
    expect($component->get('selectedUsers'))->toContain($user->id);
});

it('assigns users to a role on save', function () {
    $role = Role::create(['name' => 'Test Role', 'guard_name' => 'web']);
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Volt::test('admin.settings.role-form', ['role' => $role])
        ->set('roleName', 'Test Role')
        ->set('selectedUsers', [$user1->id, $user2->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.settings.roles'));

    expect($user1->fresh()->hasRole('Test Role'))->toBeTrue();
    expect($user2->fresh()->hasRole('Test Role'))->toBeTrue();
});

it('removes users from a role on save', function () {
    $role = Role::create(['name' => 'Test Role', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('Test Role');

    Volt::test('admin.settings.role-form', ['role' => $role])
        ->set('roleName', 'Test Role')
        ->set('selectedUsers', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->hasRole('Test Role'))->toBeFalse();
});

it('assigns users when creating a new role', function () {
    $user = User::factory()->create();

    Volt::test('admin.settings.role-form')
        ->set('roleName', 'Brand New Role')
        ->set('selectedUsers', [$user->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.settings.roles'));

    expect($user->fresh()->hasRole('Brand New Role'))->toBeTrue();
});

it('includes owner users in the user list', function () {
    $component = Volt::test('admin.settings.role-form');
    $allUsers = $component->viewData('allUsers');

    expect($allUsers->where('is_owner', true)->count())->toBeGreaterThan(0);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.roles'))
        ->assertForbidden();
});

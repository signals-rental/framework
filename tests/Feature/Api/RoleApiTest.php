<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/roles', function () {
    it('lists all roles with permissions ordered by sort_order', function () {
        $token = $this->owner->createToken('test', ['roles:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonStructure([
                'roles' => [
                    '*' => ['id', 'name', 'description', 'is_system', 'sort_order', 'permissions', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        $roles = $response->json('roles');
        expect(count($roles))->toBeGreaterThanOrEqual(5) // Admin, Operations Manager, Sales, Warehouse, Read Only from seeder
            ->and($response->json('meta.page'))->toBe(1);

        // Verify ordering by sort_order
        /** @var array<int, array<string, mixed>> $roleList */
        $roleList = $roles;
        $sortOrders = collect($roleList)->pluck('sort_order')->toArray();
        $sorted = $sortOrders;
        sort($sorted);
        expect($sortOrders)->toBe($sorted);
    });

    it('includes custom roles alongside system roles', function () {
        Role::create(['name' => 'Custom Editor', 'guard_name' => 'web', 'is_system' => false, 'sort_order' => 99]);
        $token = $this->owner->createToken('test', ['roles:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/roles')
            ->assertOk();

        /** @var array<int, array<string, mixed>> $roleData */
        $roleData = $response->json('roles');
        $roleNames = collect($roleData)->pluck('name')->toArray();
        expect($roleNames)->toContain('Custom Editor');
        expect($roleNames)->toContain('Admin');
    });

    it('includes permission names in the response', function () {
        $token = $this->owner->createToken('test', ['roles:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/roles')
            ->assertOk();

        // Admin role should have permissions
        /** @var array<int, array<string, mixed>> $permRoles */
        $permRoles = $response->json('roles');
        $adminRole = collect($permRoles)->firstWhere('name', 'Admin');
        expect($adminRole)->not->toBeNull();
        expect($adminRole['permissions'])->toBeArray();
        expect(count($adminRole['permissions']))->toBeGreaterThan(0);
    });

    it('requires roles:read ability', function () {
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/roles')
            ->assertForbidden();
    });

    it('requires roles.manage permission for non-owner users', function () {
        $user = User::factory()->create(); // no roles, no permissions
        $token = $user->createToken('test', ['roles:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/roles')
            ->assertForbidden();
    });

    it('rejects unauthenticated requests', function () {
        $this->getJson('/api/v1/roles')
            ->assertUnauthorized();
    });
});

describe('GET /api/v1/roles/{id}', function () {
    it('shows a single role with permissions', function () {
        $role = Role::query()->where('name', 'Admin')->first();
        $token = $this->owner->createToken('test', ['roles:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/roles/{$role->id}")
            ->assertOk()
            ->assertJsonStructure([
                'role' => ['id', 'name', 'description', 'is_system', 'sort_order', 'permissions', 'created_at', 'updated_at'],
            ]);

        expect($response->json('role.name'))->toBe('Admin');
        expect($response->json('role.is_system'))->toBeTrue();
        expect($response->json('role.permissions'))->toBeArray();
    });

    it('returns 404 for nonexistent role', function () {
        $token = $this->owner->createToken('test', ['roles:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/roles/99999')
            ->assertNotFound();
    });

    it('shows a custom role', function () {
        $role = Role::create(['name' => 'Custom', 'guard_name' => 'web', 'description' => 'A custom role', 'is_system' => false, 'sort_order' => 50]);
        $token = $this->owner->createToken('test', ['roles:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/roles/{$role->id}")
            ->assertOk();

        expect($response->json('role.name'))->toBe('Custom');
        expect($response->json('role.description'))->toBe('A custom role');
        expect($response->json('role.is_system'))->toBeFalse();
        expect($response->json('role.sort_order'))->toBe(50);
    });
});

describe('POST /api/v1/roles', function () {
    it('creates a new role', function () {
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/roles', [
                'name' => 'Editor',
                'description' => 'Can edit content',
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'role' => ['id', 'name', 'description', 'is_system', 'sort_order', 'permissions', 'created_at', 'updated_at'],
            ])
            ->assertJsonPath('role.name', 'Editor')
            ->assertJsonPath('role.description', 'Can edit content')
            ->assertJsonPath('role.is_system', false);

        $this->assertDatabaseHas('roles', ['name' => 'Editor']);
    });

    it('creates a role with permissions', function () {
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/roles', [
                'name' => 'Content Manager',
                'description' => 'Manages content',
                'permissions' => ['members.view', 'members.edit'],
            ])
            ->assertCreated();

        expect($response->json('role.permissions'))->toContain('members.view');
        expect($response->json('role.permissions'))->toContain('members.edit');
    });

    it('validates required name field', function () {
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/roles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique role name', function () {
        Role::create(['name' => 'Taken', 'guard_name' => 'web']);
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/roles', ['name' => 'Taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates name max length', function () {
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/roles', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates description max length', function () {
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/roles', [
                'name' => 'Valid Name',
                'description' => str_repeat('a', 1001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    });

    it('validates permissions must be an array', function () {
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/roles', [
                'name' => 'Test',
                'permissions' => 'not-an-array',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);
    });

    it('requires roles:write ability', function () {
        $token = $this->owner->createToken('test', ['roles:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/roles', ['name' => 'New Role'])
            ->assertForbidden();
    });

    it('requires roles.manage permission for non-owner users', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/roles', ['name' => 'Attempt'])
            ->assertForbidden();
    });
});

describe('PUT /api/v1/roles/{id}', function () {
    it('updates a role name', function () {
        $role = Role::create(['name' => 'OldName', 'guard_name' => 'web', 'is_system' => false]);
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/roles/{$role->id}", ['name' => 'NewName'])
            ->assertOk()
            ->assertJsonPath('role.name', 'NewName');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'NewName']);
    });

    it('updates a role description', function () {
        $role = Role::create(['name' => 'TestRole', 'guard_name' => 'web', 'is_system' => false, 'description' => 'Old desc']);
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/roles/{$role->id}", ['description' => 'New description'])
            ->assertOk()
            ->assertJsonPath('role.description', 'New description');
    });

    it('updates role permissions', function () {
        $role = Role::create(['name' => 'TestRole', 'guard_name' => 'web', 'is_system' => false]);
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/roles/{$role->id}", [
                'permissions' => ['members.view', 'members.create'],
            ])
            ->assertOk();

        expect($response->json('role.permissions'))->toContain('members.view');
        expect($response->json('role.permissions'))->toContain('members.create');
    });

    it('prevents renaming system roles', function () {
        $role = Role::query()->where('name', 'Admin')->first();
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/roles/{$role->id}", ['name' => 'Renamed Admin'])
            ->assertUnprocessable();
    });

    it('allows updating description on system roles', function () {
        $role = Role::query()->where('name', 'Admin')->first();
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/roles/{$role->id}", ['description' => 'Updated admin description'])
            ->assertOk()
            ->assertJsonPath('role.description', 'Updated admin description');
    });

    it('allows updating permissions on system roles', function () {
        $role = Role::query()->where('name', 'Read Only')->first();
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/roles/{$role->id}", [
                'permissions' => ['members.view', 'opportunities.view'],
            ])
            ->assertOk();

        expect($response->json('role.permissions'))->toContain('members.view');
        expect($response->json('role.permissions'))->toContain('opportunities.view');
    });

    it('validates unique name excluding current role', function () {
        Role::create(['name' => 'ExistingRole', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'MyRole', 'guard_name' => 'web']);
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/roles/{$role->id}", ['name' => 'ExistingRole'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows keeping the same name', function () {
        $role = Role::create(['name' => 'KeepName', 'guard_name' => 'web', 'is_system' => false]);
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/roles/{$role->id}", ['name' => 'KeepName', 'description' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('role.name', 'KeepName')
            ->assertJsonPath('role.description', 'Updated');
    });

    it('returns 404 for nonexistent role', function () {
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/roles/99999', ['name' => 'Test'])
            ->assertNotFound();
    });

    it('requires roles:write ability', function () {
        $role = Role::create(['name' => 'TestRole', 'guard_name' => 'web']);
        $token = $this->owner->createToken('test', ['roles:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/roles/{$role->id}", ['name' => 'New'])
            ->assertForbidden();
    });
});

describe('DELETE /api/v1/roles/{id}', function () {
    it('deletes a non-system role', function () {
        $role = Role::create(['name' => 'Temp', 'guard_name' => 'web', 'is_system' => false]);
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/roles/{$role->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    });

    it('prevents deleting system roles', function () {
        $role = Role::query()->where('name', 'Admin')->first();
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/roles/{$role->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    });

    it('prevents deleting roles with assigned users', function () {
        $role = Role::create(['name' => 'InUse', 'guard_name' => 'web', 'is_system' => false]);
        $user = User::factory()->create();
        $user->assignRole($role);
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/roles/{$role->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    });

    it('returns 404 for nonexistent role', function () {
        $token = $this->owner->createToken('test', ['roles:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/roles/99999')
            ->assertNotFound();
    });

    it('requires roles:write ability', function () {
        $role = Role::create(['name' => 'TestDelete', 'guard_name' => 'web', 'is_system' => false]);
        $token = $this->owner->createToken('test', ['roles:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/roles/{$role->id}")
            ->assertForbidden();
    });
});

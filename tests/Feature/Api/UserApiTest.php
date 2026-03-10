<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    Notification::fake();
});

describe('GET /api/v1/users', function () {
    it('lists users with pagination meta', function () {
        User::factory()->count(3)->create();
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonStructure([
                'users' => [
                    '*' => ['id', 'name', 'email', 'is_admin', 'is_owner', 'is_active', 'roles', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 4) // 3 + owner
            ->assertJsonPath('meta.page', 1);
    });

    it('filters by name_eq', function () {
        User::factory()->create(['name' => 'Alice Smith']);
        User::factory()->create(['name' => 'Bob Jones']);
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users?q[name_eq]=Alice Smith')
            ->assertOk();

        $users = $response->json('users');
        expect($users)->toHaveCount(1);
        expect($users[0]['name'])->toBe('Alice Smith');
    });

    it('filters by is_active_true', function () {
        User::factory()->create(['is_active' => true]);
        User::factory()->deactivated()->create();
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users?q[is_active_true]=1')
            ->assertOk();

        $users = $response->json('users');
        // Owner + 1 active user = 2 active users
        expect(collect($users)->every(fn ($u) => $u['is_active'] === true))->toBeTrue();
    });

    it('sorts by name', function () {
        User::factory()->create(['name' => 'Zara']);
        User::factory()->create(['name' => 'Amy']);
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users?sort=name')
            ->assertOk();

        $names = collect($response->json('users'))->pluck('name')->toArray();
        $sorted = $names;
        sort($sorted);
        expect($names)->toBe($sorted);
    });

    it('paginates with per_page', function () {
        User::factory()->count(5)->create();
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 6) // 5 + owner
            ->assertJsonCount(2, 'users');
    });

    it('requires users:read ability', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users')
            ->assertForbidden();
    });
});

describe('GET /api/v1/users/{id}', function () {
    it('shows a single user with roles', function () {
        $user = User::factory()->create();
        $user->assignRole('Viewer');
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'is_admin', 'is_owner', 'is_active', 'roles', 'created_at', 'updated_at'],
            ]);

        expect($response->json('user.id'))->toBe($user->id);
        expect($response->json('user.roles'))->toContain('Viewer');
    });

    it('returns 404 for nonexistent user', function () {
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users/99999')
            ->assertNotFound();
    });
});

describe('POST /api/v1/users', function () {
    it('creates and invites a user', function () {
        $token = $this->owner->createToken('test', ['users:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'roles' => ['Viewer'],
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'is_admin', 'is_owner', 'is_active', 'roles', 'created_at'],
            ])
            ->assertJsonPath('user.name', 'New User')
            ->assertJsonPath('user.email', 'newuser@example.com')
            ->assertJsonPath('user.roles', ['Viewer']);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'is_active' => true,
        ]);
    });

    it('validates required fields', function () {
        $token = $this->owner->createToken('test', ['users:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    });

    it('validates unique email', function () {
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $token = $this->owner->createToken('test', ['users:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Another User',
                'email' => 'taken@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('requires users:write ability', function () {
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ])
            ->assertForbidden();
    });
});

describe('PUT /api/v1/users/{id}', function () {
    it('updates a user name', function () {
        $user = User::factory()->create(['name' => 'Old Name']);
        $token = $this->owner->createToken('test', ['users:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/users/{$user->id}", [
                'name' => 'New Name',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'New Name');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    });

    it('performs partial update without affecting other fields', function () {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);
        $token = $this->owner->createToken('test', ['users:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/users/{$user->id}", [
                'name' => 'Updated Name',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.email', 'original@example.com');
    });
});

describe('DELETE /api/v1/users/{id}', function () {
    it('deactivates a user', function () {
        $user = User::factory()->create(['is_active' => true]);
        $token = $this->owner->createToken('test', ['users:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);
    });

    it('returns 422 when trying to deactivate the owner', function () {
        $anotherOwner = User::factory()->owner()->create();
        $token = $this->owner->createToken('test', ['users:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/users/{$anotherOwner->id}")
            ->assertUnprocessable();
    });
});

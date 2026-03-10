<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
});

describe('GET /api/v1/settings', function () {
    it('lists all settings groups', function () {
        $token = $this->user->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonStructure([
                'settings' => [
                    '*' => ['group', 'settings'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);
    });

    it('requires settings:read ability', function () {
        $token = $this->user->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings')
            ->assertForbidden();
    });

    it('rejects unauthenticated requests', function () {
        $this->getJson('/api/v1/settings')
            ->assertUnauthorized();
    });
});

describe('GET /api/v1/settings/{group}', function () {
    it('returns settings for a specific group', function () {
        $token = $this->user->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings/security')
            ->assertOk()
            ->assertJsonStructure([
                'setting' => ['group', 'settings'],
            ])
            ->assertJsonPath('setting.group', 'security');
    });

    it('returns 404 for unknown group', function () {
        $token = $this->user->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings/nonexistent')
            ->assertNotFound();
    });
});

describe('PUT /api/v1/settings/{group}', function () {
    it('updates settings for a group', function () {
        $token = $this->user->createToken('test', ['settings:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/security', [
                'settings' => [
                    'password_min_length' => 12,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('setting.group', 'security')
            ->assertJsonPath('setting.settings.password_min_length', 12);

        expect(settings('security.password_min_length'))->toBe(12);
    });

    it('requires settings:write ability', function () {
        $token = $this->user->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/security', [
                'settings' => ['password_min_length' => 12],
            ])
            ->assertForbidden();
    });

    it('validates against definition rules', function () {
        $token = $this->user->createToken('test', ['settings:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/security', [
                'settings' => [
                    'password_min_length' => 3, // min:6 rule
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password_min_length']);
    });

    it('returns 404 for unknown group', function () {
        $token = $this->user->createToken('test', ['settings:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/nonexistent', [
                'settings' => ['key' => 'value'],
            ])
            ->assertNotFound();
    });

    it('requires settings.manage permission for non-owner users', function () {
        $user = User::factory()->create(); // no role, no permissions
        $token = $user->createToken('test', ['settings:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/security', [
                'settings' => ['password_min_length' => 10],
            ])
            ->assertForbidden();
    });

    it('allows admin users with settings.manage permission', function () {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $token = $admin->createToken('test', ['settings:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/security', [
                'settings' => ['password_min_length' => 10],
            ])
            ->assertOk()
            ->assertJsonPath('setting.settings.password_min_length', 10);
    });
});

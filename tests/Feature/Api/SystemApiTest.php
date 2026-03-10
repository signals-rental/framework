<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/system/health', function () {
    it('returns health check response', function () {
        $token = $this->owner->createToken('test', ['system:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/system/health')
            ->assertOk()
            ->assertJsonStructure([
                'health' => ['status', 'timestamp'],
            ])
            ->assertJsonPath('health.status', 'ok');
    });

    it('requires system:read ability', function () {
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/system/health')
            ->assertForbidden();
    });

    it('requires system.read permission for non-owner users', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['system:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/system/health')
            ->assertForbidden();
    });

    it('allows admin users with system.read permission', function () {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $token = $admin->createToken('test', ['system:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/system/health')
            ->assertOk();
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/system/health')
            ->assertUnauthorized();
    });

    it('returns timestamp in ISO 8601 format', function () {
        $token = $this->owner->createToken('test', ['system:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/system/health')
            ->assertOk();

        $timestamp = $response->json('health.timestamp');
        expect($timestamp)->not->toBeNull();
        expect(strtotime($timestamp))->not->toBeFalse();
    });
});

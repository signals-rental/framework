<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('returns 401 for unauthenticated requests', function () {
    $this->getJson('/api/v1/system/health')
        ->assertUnauthorized();
});

it('returns 200 for authenticated requests with correct ability', function () {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('test', ['system:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health')
        ->assertOk()
        ->assertJsonStructure([
            'health' => ['status', 'timestamp'],
        ])
        ->assertJsonPath('health.status', 'ok');
});

it('returns 403 for authenticated requests with wrong ability', function () {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('test', ['users:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health')
        ->assertForbidden();
});

it('returns 403 for deactivated user', function () {
    $user = User::factory()->deactivated()->create();
    $token = $user->createToken('test', ['system:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health')
        ->assertForbidden()
        ->assertJsonPath('message', 'Your account has been deactivated.');
});

it('returns 403 for user without system.read permission and non-wildcard token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['system:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health')
        ->assertForbidden();
});

it('allows owner with wildcard token ability', function () {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('test', ['*'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health')
        ->assertOk();
});

it('allows user with system.read permission granted via role', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $token = $user->createToken('test', ['system:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health')
        ->assertOk();
});

it('returns health response in correct format', function () {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('test', ['system:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health')
        ->assertOk()
        ->assertJsonStructure([
            'health' => ['status', 'timestamp'],
        ])
        ->assertJsonPath('health.status', 'ok');
});

<?php

use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('includes rate limit headers on API responses', function () {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('test', ['system:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health');

    $response->assertOk()
        ->assertHeader('X-RateLimit-Limit')
        ->assertHeader('X-RateLimit-Remaining');
});

it('returns 429 when rate limit is exceeded for authenticated users', function () {
    app(SettingsService::class)->set('api.rate_limit', 2, 'integer');

    $user = User::factory()->owner()->create();
    $token = $user->createToken('test', ['system:read'])->plainTextToken;

    // First two requests should succeed
    for ($i = 0; $i < 2; $i++) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/system/health')
            ->assertOk();
    }

    // Third request should be rate limited
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('uses settings-driven rate limit value', function () {
    app(SettingsService::class)->set('api.rate_limit', 3, 'integer');

    $user = User::factory()->owner()->create();
    $token = $user->createToken('test', ['system:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health');

    $response->assertOk()
        ->assertHeader('X-RateLimit-Limit', 3);
});

it('applies default rate limit when no setting is stored', function () {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('test', ['system:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health');

    $response->assertOk()
        ->assertHeader('X-RateLimit-Limit', 60);
});

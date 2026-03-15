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

it('applies per-token rate limit when set on the token', function () {
    $user = User::factory()->owner()->create();
    $accessToken = $user->createToken('test', ['system:read']);

    // Set per-token rate limit
    $accessToken->accessToken->forceFill(['rate_limit_per_minute' => 5])->save();

    $response = $this->withHeader('Authorization', "Bearer {$accessToken->plainTextToken}")
        ->getJson('/api/v1/system/health');

    $response->assertOk()
        ->assertHeader('X-RateLimit-Limit', 5);
});

it('enforces per-token rate limit independently', function () {
    $user = User::factory()->owner()->create();
    $accessToken = $user->createToken('test', ['system:read']);
    $accessToken->accessToken->forceFill(['rate_limit_per_minute' => 2])->save();

    // First two requests should succeed
    for ($i = 0; $i < 2; $i++) {
        $this->withHeader('Authorization', "Bearer {$accessToken->plainTextToken}")
            ->getJson('/api/v1/system/health')
            ->assertOk();
    }

    // Third request should be rate limited
    $this->withHeader('Authorization', "Bearer {$accessToken->plainTextToken}")
        ->getJson('/api/v1/system/health')
        ->assertTooManyRequests();
});

it('falls back to global rate limit when token has no per-token limit', function () {
    app(SettingsService::class)->set('api.rate_limit', 15, 'integer');

    $user = User::factory()->owner()->create();
    $token = $user->createToken('test', ['system:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/system/health');

    $response->assertOk()
        ->assertHeader('X-RateLimit-Limit', 15);
});

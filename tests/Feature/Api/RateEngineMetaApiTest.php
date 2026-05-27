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

describe('GET /api/v1/rate_engine/strategies', function () {
    it('lists the registered calculation strategies', function () {
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_engine/strategies')
            ->assertOk()
            ->assertJsonStructure([
                'strategies' => [
                    '*' => ['identifier', 'label', 'allowed_base_periods', 'supports_multiplier', 'supports_factor'],
                ],
            ]);

        $identifiers = collect((array) $response->json('strategies'))->pluck('identifier')->all();
        expect($identifiers)->toContain('period', 'fixed', 'hybrid');
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/rate_engine/strategies')->assertUnauthorized();
    });

    it('requires rates:read ability', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_engine/strategies')
            ->assertForbidden();
    });
});

describe('GET /api/v1/rate_engine/modifiers', function () {
    it('lists the registered modifiers ordered by priority', function () {
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_engine/modifiers')
            ->assertOk()
            ->assertJsonStructure([
                'modifiers' => [
                    '*' => ['identifier', 'label', 'priority'],
                ],
            ]);

        $identifiers = collect((array) $response->json('modifiers'))->pluck('identifier')->all();
        expect($identifiers)->toBe(['multiplier', 'factor']);
    });
});

describe('GET /api/v1/rate_engine/presets', function () {
    it('lists the framework rate presets', function () {
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_engine/presets')
            ->assertOk()
            ->assertJsonStructure([
                'presets' => [
                    '*' => ['slug', 'name', 'description', 'calculation_strategy', 'base_period', 'enabled_modifiers', 'strategy_config', 'modifier_configs'],
                ],
            ]);

        expect($response->json('presets'))->toHaveCount(11);

        $daily = collect((array) $response->json('presets'))->firstWhere('slug', 'daily-rate');
        expect($daily['calculation_strategy'])->toBe('period');
        expect($daily['base_period'])->toBe('daily');
    });
});

describe('GET /api/v1/rate_engine/schema', function () {
    it('composes the form sections for a strategy and its enabled modifiers', function () {
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_engine/schema?strategy=period&modifiers[]=multiplier&modifiers[]=factor')
            ->assertOk()
            ->assertJsonStructure([
                'sections' => [
                    '*' => ['key', 'label', 'fields'],
                ],
            ]);

        $keys = collect((array) $response->json('sections'))->pluck('key')->all();
        expect($keys)->toContain('options', 'multiplier', 'factor');
    });

    it('requires the strategy parameter', function () {
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_engine/schema')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['strategy']);
    });

    it('rejects an unknown strategy', function () {
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_engine/schema?strategy=bogus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['strategy']);
    });
});

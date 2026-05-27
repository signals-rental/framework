<?php

use App\Enums\BasePeriod;
use App\Models\RateDefinition;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/rate_definitions', function () {
    it('lists rate definitions with pagination meta', function () {
        RateDefinition::factory()->count(3)->create();
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_definitions')
            ->assertOk()
            ->assertJsonStructure([
                'rate_definitions' => [
                    '*' => [
                        'id', 'name', 'description', 'calculation_strategy',
                        'calculation_strategy_name', 'base_period', 'base_period_name',
                        'enabled_modifiers', 'strategy_config', 'modifier_configs',
                        'is_preset', 'preset_slug', 'cloned_from_id', 'created_at', 'updated_at',
                    ],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 3);
    });

    it('filters by calculation_strategy', function () {
        RateDefinition::factory()->create();
        RateDefinition::factory()->fixed()->create();
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_definitions?q[calculation_strategy_eq]=fixed')
            ->assertOk();

        expect($response->json('rate_definitions'))->toHaveCount(1);
        expect($response->json('rate_definitions.0.calculation_strategy'))->toBe('fixed');
    });

    it('filters by base_period', function () {
        RateDefinition::factory()->create(['base_period' => BasePeriod::Daily]);
        RateDefinition::factory()->create(['base_period' => BasePeriod::Weekly]);
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_definitions?q[base_period_eq]=weekly')
            ->assertOk();

        expect($response->json('rate_definitions'))->toHaveCount(1);
        expect($response->json('rate_definitions.0.base_period'))->toBe('weekly');
    });

    it('filters by is_preset', function () {
        RateDefinition::factory()->preset()->create();
        RateDefinition::factory()->create();
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_definitions?q[is_preset_true]=1')
            ->assertOk();

        expect($response->json('rate_definitions'))->toHaveCount(1);
        expect($response->json('rate_definitions.0.is_preset'))->toBeTrue();
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/rate_definitions')->assertUnauthorized();
    });

    it('requires rates:read ability', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_definitions')
            ->assertForbidden();
    });
});

describe('GET /api/v1/rate_definitions/{id}', function () {
    it('shows a single rate definition', function () {
        $definition = RateDefinition::factory()->create(['name' => 'Summer Daily']);
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/rate_definitions/{$definition->id}")
            ->assertOk()
            ->assertJsonPath('rate_definition.name', 'Summer Daily');
    });

    it('returns 404 for a non-existent rate definition', function () {
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rate_definitions/99999')
            ->assertNotFound();
    });
});

describe('POST /api/v1/rate_definitions', function () {
    it('creates a rate definition', function () {
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rate_definitions', [
                'name' => 'Flat Fee',
                'calculation_strategy' => 'fixed',
            ])
            ->assertCreated()
            ->assertJsonPath('rate_definition.name', 'Flat Fee')
            ->assertJsonPath('rate_definition.calculation_strategy', 'fixed')
            ->assertJsonPath('rate_definition.is_preset', false);

        $this->assertDatabaseHas('rate_definitions', ['name' => 'Flat Fee', 'is_preset' => false]);
    });

    it('validates required fields', function () {
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rate_definitions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'calculation_strategy']);
    });

    it('requires rates:write ability', function () {
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rate_definitions', ['name' => 'X', 'calculation_strategy' => 'fixed'])
            ->assertForbidden();
    });
});

describe('PUT /api/v1/rate_definitions/{id}', function () {
    it('updates a rate definition', function () {
        $definition = RateDefinition::factory()->create(['name' => 'Old']);
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/rate_definitions/{$definition->id}", ['name' => 'New'])
            ->assertOk()
            ->assertJsonPath('rate_definition.name', 'New');
    });
});

describe('DELETE /api/v1/rate_definitions/{id}', function () {
    it('deletes a rate definition', function () {
        $definition = RateDefinition::factory()->create();
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/rate_definitions/{$definition->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('rate_definitions', ['id' => $definition->id]);
    });
});

describe('POST /api/v1/rate_definitions/{id}/duplicate', function () {
    it('duplicates a rate definition', function () {
        $definition = RateDefinition::factory()->create(['name' => 'Original']);
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/rate_definitions/{$definition->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('rate_definition.name', 'Original (Copy)')
            ->assertJsonPath('rate_definition.is_preset', false)
            ->assertJsonPath('rate_definition.cloned_from_id', $definition->id);

        $this->assertDatabaseHas('rate_definitions', ['name' => 'Original (Copy)', 'cloned_from_id' => $definition->id]);
    });

    it('requires rates:write ability', function () {
        $definition = RateDefinition::factory()->create();
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/rate_definitions/{$definition->id}/duplicate")
            ->assertForbidden();
    });
});

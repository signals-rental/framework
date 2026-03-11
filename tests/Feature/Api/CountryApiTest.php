<?php

use App\Models\Country;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/countries', function () {
    it('lists countries with pagination', function () {
        Country::factory()->count(3)->create();
        $token = $this->owner->createToken('test', ['countries:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/countries')
            ->assertOk()
            ->assertJsonStructure([
                'countries' => [
                    '*' => ['id', 'code', 'code3', 'name', 'currency_code', 'is_active'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);
    });

    it('filters by name', function () {
        Country::factory()->create(['name' => 'United Kingdom', 'code' => 'GB', 'code3' => 'GBR']);
        Country::factory()->create(['name' => 'France', 'code' => 'FR', 'code3' => 'FRA']);
        $token = $this->owner->createToken('test', ['countries:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/countries?q[name_eq]=France')
            ->assertOk();

        expect($response->json('countries'))->toHaveCount(1);
        expect($response->json('countries.0.name'))->toBe('France');
    });

    it('requires countries:read ability', function () {
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/countries')
            ->assertForbidden();
    });
});

describe('GET /api/v1/countries/{id}', function () {
    it('shows a single country', function () {
        $country = Country::factory()->create(['name' => 'Germany', 'code' => 'DE', 'code3' => 'DEU']);
        $token = $this->owner->createToken('test', ['countries:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/countries/{$country->id}")
            ->assertOk()
            ->assertJsonPath('country.name', 'Germany')
            ->assertJsonPath('country.code', 'DE');
    });
});

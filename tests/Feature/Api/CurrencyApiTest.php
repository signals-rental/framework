<?php

use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(CurrencySeeder::class);
    $this->user = User::factory()->owner()->create();
    Sanctum::actingAs($this->user, ['*']);
});

describe('GET /api/v1/currencies', function () {
    it('lists currencies', function () {
        $this->getJson('/api/v1/currencies')
            ->assertOk()
            ->assertJsonStructure([
                'currencies' => [
                    '*' => ['id', 'code', 'name', 'symbol', 'decimal_places', 'is_enabled'],
                ],
                'meta',
            ]);
    });

    it('filters by enabled status', function () {
        $response = $this->getJson('/api/v1/currencies?q[is_enabled_eq]=true')
            ->assertOk();

        /** @var array<int, array<string, mixed>> $currencies */
        $currencies = $response->json('currencies');
        expect(collect($currencies)->every(fn ($c) => $c['is_enabled'] === true))->toBeTrue();
    });

    it('filters by code', function () {
        $response = $this->getJson('/api/v1/currencies?q[code_eq]=GBP')
            ->assertOk();

        expect($response->json('currencies'))->toHaveCount(1);
        expect($response->json('currencies.0.code'))->toBe('GBP');
    });

    it('sorts by code', function () {
        $response = $this->getJson('/api/v1/currencies?sort=code&direction=asc&per_page=100')
            ->assertOk();

        /** @var array<int, array<string, mixed>> $currencyData */
        $currencyData = $response->json('currencies');
        $codes = collect($currencyData)->pluck('code')->all();
        $sorted = $codes;
        sort($sorted);
        expect($codes)->toBe($sorted);
    });
});

describe('GET /api/v1/currencies/{id}', function () {
    it('shows a single currency', function () {
        $currency = \App\Models\Currency::where('code', 'GBP')->first();

        $this->getJson("/api/v1/currencies/{$currency->id}")
            ->assertOk()
            ->assertJsonPath('currency.code', 'GBP')
            ->assertJsonPath('currency.name', 'British Pound Sterling')
            ->assertJsonStructure([
                'currency' => [
                    'id', 'code', 'name', 'symbol', 'decimal_places',
                    'symbol_position', 'thousand_separator', 'decimal_separator',
                    'is_enabled', 'created_at', 'updated_at',
                ],
            ]);
    });

    it('returns 404 for non-existent currency', function () {
        $this->getJson('/api/v1/currencies/99999')
            ->assertNotFound();
    });
});

describe('authentication', function () {
    it('requires authentication', function () {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/currencies')->assertUnauthorized();
    });
});

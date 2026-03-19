<?php

use App\Models\ExchangeRate;
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

describe('GET /api/v1/exchange_rates', function () {
    it('lists exchange rates', function () {
        ExchangeRate::factory()->create();

        $this->getJson('/api/v1/exchange_rates')
            ->assertOk()
            ->assertJsonStructure([
                'exchange_rates' => [
                    '*' => ['id', 'source_currency_code', 'target_currency_code', 'rate', 'inverse_rate', 'source', 'effective_at'],
                ],
                'meta',
            ]);
    });

    it('filters by source currency code', function () {
        ExchangeRate::factory()->create(['source_currency_code' => 'GBP', 'target_currency_code' => 'USD']);
        ExchangeRate::factory()->create(['source_currency_code' => 'EUR', 'target_currency_code' => 'USD']);

        $response = $this->getJson('/api/v1/exchange_rates?q[source_currency_code_eq]=GBP')
            ->assertOk();

        /** @var array<int, array<string, mixed>> $rates */
        $rates = $response->json('exchange_rates');
        expect(collect($rates)->every(fn ($r) => $r['source_currency_code'] === 'GBP'))->toBeTrue();
    });
});

describe('GET /api/v1/exchange_rates/{id}', function () {
    it('shows a single exchange rate', function () {
        $rate = ExchangeRate::factory()->create();

        $this->getJson("/api/v1/exchange_rates/{$rate->id}")
            ->assertOk()
            ->assertJsonPath('exchange_rate.id', $rate->id)
            ->assertJsonStructure([
                'exchange_rate' => [
                    'id', 'source_currency_code', 'target_currency_code',
                    'rate', 'inverse_rate', 'source', 'effective_at',
                    'expires_at', 'created_at', 'updated_at',
                ],
            ]);
    });
});

describe('POST /api/v1/exchange_rates', function () {
    it('creates an exchange rate', function () {
        $this->postJson('/api/v1/exchange_rates', [
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'EUR',
            'rate' => '1.16000000',
            'source' => 'manual',
        ])
            ->assertCreated()
            ->assertJsonPath('exchange_rate.source_currency_code', 'GBP')
            ->assertJsonPath('exchange_rate.target_currency_code', 'EUR')
            ->assertJsonPath('exchange_rate.rate', '1.16000000');

        expect(ExchangeRate::where('source_currency_code', 'GBP')
            ->where('target_currency_code', 'EUR')
            ->exists())->toBeTrue();
    });

    it('auto-computes inverse rate if not provided', function () {
        $response = $this->postJson('/api/v1/exchange_rates', [
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'EUR',
            'rate' => '1.16000000',
        ])->assertCreated();

        // 1 / 1.16 ≈ 0.86206896
        $inverseRate = $response->json('exchange_rate.inverse_rate');
        expect((float) $inverseRate)->toBeGreaterThan(0.86)
            ->and((float) $inverseRate)->toBeLessThan(0.87);
    });

    it('uses provided inverse rate when given', function () {
        $this->postJson('/api/v1/exchange_rates', [
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'EUR',
            'rate' => '1.16000000',
            'inverse_rate' => '0.86206897',
        ])
            ->assertCreated()
            ->assertJsonPath('exchange_rate.inverse_rate', '0.86206897');
    });

    it('validates required fields', function () {
        $this->postJson('/api/v1/exchange_rates', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_currency_code', 'target_currency_code', 'rate']);
    });

    it('validates currency codes exist', function () {
        $this->postJson('/api/v1/exchange_rates', [
            'source_currency_code' => 'XXX',
            'target_currency_code' => 'YYY',
            'rate' => '1.00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_currency_code', 'target_currency_code']);
    });

    it('validates source and target are different', function () {
        $this->postJson('/api/v1/exchange_rates', [
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'GBP',
            'rate' => '1.00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_currency_code']);
    });
});

describe('PUT /api/v1/exchange_rates/{id}', function () {
    it('updates an exchange rate', function () {
        $rate = ExchangeRate::factory()->create([
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'USD',
            'rate' => '1.27000000',
        ]);

        $this->putJson("/api/v1/exchange_rates/{$rate->id}", [
            'rate' => '1.30000000',
        ])
            ->assertOk()
            ->assertOk();

        $updatedRate = ExchangeRate::find($rate->id);
        expect((float) $updatedRate->rate)->toBe(1.3);
        // Inverse should be auto-recomputed
        expect((float) $updatedRate->inverse_rate)->toBeGreaterThan(0.76)
            ->and((float) $updatedRate->inverse_rate)->toBeLessThan(0.78);
    });

    it('partially updates without losing other fields', function () {
        $rate = ExchangeRate::factory()->create([
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'USD',
            'rate' => '1.27000000',
            'source' => 'manual',
        ]);

        $this->putJson("/api/v1/exchange_rates/{$rate->id}", [
            'source' => 'api',
        ])
            ->assertOk()
            ->assertJsonPath('exchange_rate.source', 'api')
            ->assertOk();

        $rate = ExchangeRate::find($rate->id);
        expect((float) $rate->rate)->toBe(1.27);
    });
});

describe('DELETE /api/v1/exchange_rates/{id}', function () {
    it('deletes an exchange rate', function () {
        $rate = ExchangeRate::factory()->create();

        $this->deleteJson("/api/v1/exchange_rates/{$rate->id}")
            ->assertNoContent();

        expect(ExchangeRate::find($rate->id))->toBeNull();
    });
});

describe('authentication', function () {
    it('requires authentication for all endpoints', function () {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/exchange_rates')->assertUnauthorized();
        $this->postJson('/api/v1/exchange_rates', [])->assertUnauthorized();
    });
});

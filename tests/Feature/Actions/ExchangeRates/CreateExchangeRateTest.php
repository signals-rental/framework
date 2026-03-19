<?php

use App\Actions\ExchangeRates\CreateExchangeRate;
use App\Actions\ExchangeRates\DeleteExchangeRate;
use App\Actions\ExchangeRates\UpdateExchangeRate;
use App\Data\ExchangeRates\CreateExchangeRateData;
use App\Data\ExchangeRates\UpdateExchangeRateData;
use App\Models\ExchangeRate;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(CurrencySeeder::class);
});

describe('CreateExchangeRate', function () {
    it('creates exchange rate with auto-computed inverse', function () {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);

        $dto = CreateExchangeRateData::from([
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'EUR',
            'rate' => '1.16000000',
        ]);

        $result = (new CreateExchangeRate)($dto);

        expect($result->source_currency_code)->toBe('GBP');
        expect($result->target_currency_code)->toBe('EUR');
        expect((float) $result->inverse_rate)->toBeGreaterThan(0.86)
            ->and((float) $result->inverse_rate)->toBeLessThan(0.87);
    });

    it('creates exchange rate with explicit inverse', function () {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);

        $dto = CreateExchangeRateData::from([
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'EUR',
            'rate' => '1.16000000',
            'inverse_rate' => '0.86206897',
        ]);

        $result = (new CreateExchangeRate)($dto);

        expect($result->inverse_rate)->toBe('0.86206897');
    });

    it('rejects zero rate', function () {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);

        $dto = CreateExchangeRateData::from([
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'EUR',
            'rate' => '0',
        ]);

        (new CreateExchangeRate)($dto);
    })->throws(\InvalidArgumentException::class, 'Exchange rate cannot be zero');

    it('denies unpermissioned user', function () {
        $user = User::factory()->create();
        $user->assignRole('Read Only');
        $this->actingAs($user);

        $dto = CreateExchangeRateData::from([
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'EUR',
            'rate' => '1.16000000',
        ]);

        (new CreateExchangeRate)($dto);
    })->throws(\Illuminate\Auth\Access\AuthorizationException::class);
});

describe('UpdateExchangeRate', function () {
    it('auto-recomputes inverse when rate changed', function () {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);

        $rate = ExchangeRate::factory()->create([
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'USD',
            'rate' => '1.27000000',
            'inverse_rate' => '0.78740157',
        ]);

        $dto = UpdateExchangeRateData::from(['rate' => '1.30000000']);
        $result = (new UpdateExchangeRate)($rate, $dto);

        expect((float) $result->rate)->toBe(1.3);
        expect((float) $result->inverse_rate)->toBeGreaterThan(0.76)
            ->and((float) $result->inverse_rate)->toBeLessThan(0.78);
    });

    it('uses explicit inverse when provided', function () {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);

        $rate = ExchangeRate::factory()->create();

        $dto = UpdateExchangeRateData::from([
            'rate' => '1.30000000',
            'inverse_rate' => '0.76923077',
        ]);
        $result = (new UpdateExchangeRate)($rate, $dto);

        expect($result->inverse_rate)->toBe('0.76923077');
    });

    it('does not recompute inverse when only non-rate fields change', function () {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);

        $rate = ExchangeRate::factory()->create([
            'rate' => '1.27000000',
            'inverse_rate' => '0.78740157',
            'source' => 'manual',
        ]);

        $dto = UpdateExchangeRateData::from(['source' => 'api']);
        $result = (new UpdateExchangeRate)($rate, $dto);

        expect($result->source)->toBe('api');
        expect($result->inverse_rate)->toBe('0.78740157');
    });

    it('rejects zero rate on update', function () {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);

        $rate = ExchangeRate::factory()->create();

        $dto = UpdateExchangeRateData::from(['rate' => '0']);

        (new UpdateExchangeRate)($rate, $dto);
    })->throws(\InvalidArgumentException::class, 'Exchange rate cannot be zero');
});

describe('DeleteExchangeRate', function () {
    it('deletes an exchange rate', function () {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);

        $rate = ExchangeRate::factory()->create();

        (new DeleteExchangeRate)($rate);

        expect(ExchangeRate::find($rate->id))->toBeNull();
    });

    it('denies unpermissioned user', function () {
        $user = User::factory()->create();
        $user->assignRole('Read Only');
        $this->actingAs($user);

        $rate = ExchangeRate::factory()->create();

        (new DeleteExchangeRate)($rate);
    })->throws(\Illuminate\Auth\Access\AuthorizationException::class);
});

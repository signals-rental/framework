<?php

use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use App\Services\SettingsService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);

    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'USD',
        'rate' => '1.27000000',
        'inverse_rate' => '0.78740157',
        'effective_at' => Carbon::now(),
        'expires_at' => null,
        'source' => 'manual',
    ]);

    $this->service = app(CurrencyService::class);
});

it('converts GBP to USD in minor units', function () {
    // 10000 minor units = £100.00, at rate 1.27 = $127.00 = 12700 minor units
    $result = $this->service->convert(10000, 'GBP', 'USD');

    expect($result)->toBe(12700);
});

it('returns same amount when converting same currency', function () {
    $result = $this->service->convert(10000, 'GBP', 'GBP');

    expect($result)->toBe(10000);
});

it('uses inverse rate when direct rate not found', function () {
    // 12700 minor units = $127.00, at inverse rate 0.78740157 = £100.00 = 10000 minor units
    $result = $this->service->convert(12700, 'USD', 'GBP');

    expect($result)->toBe(10000);
});

it('triangulates through base currency when no direct or inverse rate exists', function () {
    // Set up: GBP→USD already exists (rate 1.27), add USD→JPY
    app(SettingsService::class)->set('company.base_currency', 'USD');

    ExchangeRate::factory()->create([
        'source_currency_code' => 'USD',
        'target_currency_code' => 'JPY',
        'rate' => '150.00000000',
        'inverse_rate' => '0.00666667',
        'effective_at' => Carbon::now(),
        'expires_at' => null,
    ]);

    // GBP→JPY should triangulate via: GBP→USD (1.27) * USD→JPY (150) = 190.5
    $rate = $this->service->getRate('GBP', 'JPY');
    expect((float) $rate)->toBeGreaterThan(190.0)
        ->and((float) $rate)->toBeLessThan(191.0);
});

it('converts via triangulation using the correct rate', function () {
    app(SettingsService::class)->set('company.base_currency', 'USD');

    ExchangeRate::factory()->create([
        'source_currency_code' => 'USD',
        'target_currency_code' => 'EUR',
        'rate' => '0.92000000',
        'inverse_rate' => '1.08695652',
        'effective_at' => Carbon::now(),
        'expires_at' => null,
    ]);

    // GBP→EUR via USD: 1.27 * 0.92 = 1.1684
    // £100 (10000 minor) at 1.1684 = €116.84 (11684 minor)
    $result = $this->service->convert(10000, 'GBP', 'EUR');
    expect($result)->toBe(11684);
});

it('throws RuntimeException when no rate exists and triangulation fails', function () {
    app(SettingsService::class)->set('company.base_currency', 'USD');

    // No USD→JPY rate exists, so GBP→JPY should still fail
    $this->service->convert(10000, 'GBP', 'JPY');
})->throws(RuntimeException::class, 'No exchange rate found for GBP to JPY');

it('throws RuntimeException when no rate exists', function () {
    $this->service->convert(10000, 'EUR', 'JPY');
})->throws(RuntimeException::class);

it('returns rate of 1 when getRate is called for the same currency', function () {
    // convert() short-circuits same-currency before calling getRate, so exercise
    // getRate directly to cover its own from === to branch.
    expect($this->service->getRate('GBP', 'GBP'))->toBe('1.00000000');
});

it('gets the base currency from settings', function () {
    app(SettingsService::class)->set('company.base_currency', 'GBP');

    $baseCurrency = $this->service->baseCurrency();

    expect($baseCurrency->code)->toBe('GBP');
    expect($baseCurrency->name)->toBe('British Pound Sterling');
});

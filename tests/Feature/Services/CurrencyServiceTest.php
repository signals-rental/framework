<?php

use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use App\Services\SettingsService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ExchangeRateSeeder;
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

it('uses the rate that was effective at a given historical date', function () {
    // Two effective-dated GBP→EUR rates: an older one and a newer one.
    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'EUR',
        'rate' => '1.20000000',
        'inverse_rate' => '0.83333333',
        'effective_at' => Carbon::parse('2026-01-01T00:00:00Z'),
        'expires_at' => null,
    ]);

    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'EUR',
        'rate' => '1.30000000',
        'inverse_rate' => '0.76923077',
        'effective_at' => Carbon::parse('2026-03-01T00:00:00Z'),
        'expires_at' => null,
    ]);

    // As of 2026-02-01 only the 1.20 rate is effective.
    expect($this->service->getRate('GBP', 'EUR', Carbon::parse('2026-02-01T00:00:00Z')))
        ->toBe('1.20000000');

    // £100.00 (10000 minor) at 1.20 = €120.00 (12000 minor).
    expect($this->service->convert(10000, 'GBP', 'EUR', Carbon::parse('2026-02-01T00:00:00Z')))
        ->toBe(12000);
});

it('uses the most recent effective rate when several are effective at the date', function () {
    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'EUR',
        'rate' => '1.20000000',
        'inverse_rate' => '0.83333333',
        'effective_at' => Carbon::parse('2026-01-01T00:00:00Z'),
        'expires_at' => null,
    ]);

    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'EUR',
        'rate' => '1.30000000',
        'inverse_rate' => '0.76923077',
        'effective_at' => Carbon::parse('2026-03-01T00:00:00Z'),
        'expires_at' => null,
    ]);

    // As of 2026-04-01 both rows are effective; the latest effective_at wins.
    expect($this->service->getRate('GBP', 'EUR', Carbon::parse('2026-04-01T00:00:00Z')))
        ->toBe('1.30000000');

    // £100.00 (10000 minor) at 1.30 = €130.00 (13000 minor).
    expect($this->service->convert(10000, 'GBP', 'EUR', Carbon::parse('2026-04-01T00:00:00Z')))
        ->toBe(13000);
});

it('ignores a rate that is not yet effective', function () {
    // Only a future-dated GBP→EUR rate exists; no inverse and base currency
    // is GBP so triangulation cannot help — conversion at "now" must fail.
    app(SettingsService::class)->set('company.base_currency', 'GBP');

    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'EUR',
        'rate' => '1.30000000',
        'inverse_rate' => '0.76923077',
        'effective_at' => Carbon::now()->addMonth(),
        'expires_at' => null,
    ]);

    $this->service->getRate('GBP', 'EUR');
})->throws(RuntimeException::class, 'No exchange rate found for GBP to EUR');

it('ignores a rate whose expiry has passed', function () {
    // An expired GBP→EUR rate must not be resolved at "now".
    app(SettingsService::class)->set('company.base_currency', 'GBP');

    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'EUR',
        'rate' => '1.30000000',
        'inverse_rate' => '0.76923077',
        'effective_at' => Carbon::now()->subMonths(2),
        'expires_at' => Carbon::now()->subMonth(),
    ]);

    $this->service->getRate('GBP', 'EUR');
})->throws(RuntimeException::class, 'No exchange rate found for GBP to EUR');

it('resolves a rate that is within its bounded effective window', function () {
    // A GBP→EUR rate effective for a window that includes "now".
    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'EUR',
        'rate' => '1.18000000',
        'inverse_rate' => '0.84745763',
        'effective_at' => Carbon::now()->subMonth(),
        'expires_at' => Carbon::now()->addMonth(),
    ]);

    expect($this->service->getRate('GBP', 'EUR'))->toBe('1.18000000');
});

describe('ExchangeRateSeeder', function () {
    it('seeds baseline effective-dated rates against the base currency', function () {
        // The CurrencyServiceTest beforeEach already seeds currencies; run the
        // exchange-rate seeder on top and assert the baseline rows exist.
        $this->seed(ExchangeRateSeeder::class);

        // Query the seeder's specific effective-dated row (2026-01-01) so the
        // assertion does not depend on the beforeEach GBP→USD rate.
        $gbpUsd = ExchangeRate::query()
            ->forPair('GBP', 'USD')
            ->where('source', 'manual')
            ->where('effective_at', Carbon::parse('2026-01-01T00:00:00Z'))
            ->first();

        expect($gbpUsd)->not->toBeNull()
            ->and($gbpUsd->rate)->toBe('1.27000000')
            ->and($gbpUsd->expires_at)->toBeNull();

        $gbpEur = ExchangeRate::query()
            ->forPair('GBP', 'EUR')
            ->where('source', 'manual')
            ->where('effective_at', Carbon::parse('2026-01-01T00:00:00Z'))
            ->first();

        expect($gbpEur)->not->toBeNull()
            ->and($gbpEur->rate)->toBe('1.18000000');
    });

    it('is idempotent when run twice', function () {
        $this->seed(ExchangeRateSeeder::class);
        $countAfterFirst = ExchangeRate::query()->where('source', 'manual')->count();

        $this->seed(ExchangeRateSeeder::class);
        $countAfterSecond = ExchangeRate::query()->where('source', 'manual')->count();

        expect($countAfterSecond)->toBe($countAfterFirst);
    });
});

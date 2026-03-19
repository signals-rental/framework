<?php

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;

it('creates an exchange rate with factory', function () {
    $rate = ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'USD',
        'rate' => '1.27000000',
        'inverse_rate' => '0.78740157',
        'source' => 'manual',
    ]);

    expect($rate)->toBeInstanceOf(ExchangeRate::class);
    expect($rate->source_currency_code)->toBe('GBP');
    expect($rate->target_currency_code)->toBe('USD');
    expect($rate->source)->toBe('manual');
});

it('scopes to effective rates at a given date', function () {
    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'USD',
        'effective_at' => Carbon::yesterday(),
        'expires_at' => null,
    ]);

    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'EUR',
        'effective_at' => Carbon::tomorrow(),
        'expires_at' => null,
    ]);

    $effective = ExchangeRate::query()->effectiveAt(Carbon::now())->get();

    expect($effective)->toHaveCount(1);
    expect($effective->first()->target_currency_code)->toBe('USD');
});

it('scopes to currency pair', function () {
    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'USD',
        'effective_at' => Carbon::now(),
    ]);

    ExchangeRate::factory()->create([
        'source_currency_code' => 'EUR',
        'target_currency_code' => 'USD',
        'effective_at' => Carbon::now(),
    ]);

    $gbpToUsd = ExchangeRate::query()->forPair('GBP', 'USD')->get();

    expect($gbpToUsd)->toHaveCount(1);
    expect($gbpToUsd->first()->source_currency_code)->toBe('GBP');
});

it('excludes expired rates', function () {
    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'USD',
        'effective_at' => Carbon::now()->subDays(7),
        'expires_at' => Carbon::yesterday(),
    ]);

    ExchangeRate::factory()->create([
        'source_currency_code' => 'GBP',
        'target_currency_code' => 'EUR',
        'effective_at' => Carbon::now()->subDays(7),
        'expires_at' => null,
    ]);

    $effective = ExchangeRate::query()->effectiveAt(Carbon::now())->get();

    expect($effective)->toHaveCount(1);
    expect($effective->first()->target_currency_code)->toBe('EUR');
});

it('casts rate and inverse_rate to string', function () {
    $rate = ExchangeRate::factory()->create([
        'rate' => '1.27000000',
        'inverse_rate' => '0.78740157',
    ]);

    $rate->refresh();

    expect($rate->rate)->toBeString();
    expect($rate->inverse_rate)->toBeString();
});

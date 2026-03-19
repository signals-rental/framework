<?php

use App\Models\Currency;
use Illuminate\Database\QueryException;

it('creates a currency with factory', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'name' => 'British Pound Sterling',
        'symbol' => '£',
    ]);

    expect($currency->code)->toBe('GBP');
    expect($currency->name)->toBe('British Pound Sterling');
    expect($currency->symbol)->toBe('£');
    expect($currency)->toBeInstanceOf(Currency::class);
});

it('casts is_enabled to boolean', function () {
    $currency = Currency::factory()->create(['is_enabled' => true]);

    expect($currency->is_enabled)->toBeTrue()->toBeBool();

    $disabled = Currency::factory()->create(['is_enabled' => false]);

    expect($disabled->is_enabled)->toBeFalse()->toBeBool();
});

it('casts decimal_places to integer', function () {
    $currency = Currency::factory()->create(['decimal_places' => 2]);

    expect($currency->decimal_places)->toBe(2)->toBeInt();
});

it('scopes to enabled currencies only', function () {
    Currency::factory()->create(['code' => 'GBP', 'is_enabled' => true]);
    Currency::factory()->create(['code' => 'USD', 'is_enabled' => true]);
    Currency::factory()->create(['code' => 'JPY', 'is_enabled' => false]);

    $enabled = Currency::query()->enabled()->get();

    expect($enabled)->toHaveCount(2);
    expect($enabled->pluck('code')->toArray())->each->toBeIn(['GBP', 'USD']);
});

it('has unique code constraint', function () {
    Currency::factory()->create(['code' => 'GBP']);

    Currency::factory()->create(['code' => 'GBP']);
})->throws(QueryException::class);

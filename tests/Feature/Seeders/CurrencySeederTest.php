<?php

use App\Models\Currency;
use Database\Seeders\CurrencySeeder;

it('seeds currencies into the database', function () {
    $this->seed(CurrencySeeder::class);

    expect(Currency::query()->count())->toBeGreaterThan(0);
});

it('marks GBP, USD, EUR as enabled', function () {
    $this->seed(CurrencySeeder::class);

    $enabled = Currency::query()->enabled()->pluck('code')->toArray();

    expect($enabled)->toContain('GBP');
    expect($enabled)->toContain('USD');
    expect($enabled)->toContain('EUR');
    expect($enabled)->toHaveCount(3);
});

it('marks other currencies as disabled', function () {
    $this->seed(CurrencySeeder::class);

    $disabled = Currency::query()->where('is_enabled', false)->get();

    expect($disabled->count())->toBeGreaterThan(0);
    expect($disabled->pluck('code')->toArray())->not->toContain('GBP');
    expect($disabled->pluck('code')->toArray())->not->toContain('USD');
    expect($disabled->pluck('code')->toArray())->not->toContain('EUR');
});

it('is idempotent', function () {
    $this->seed(CurrencySeeder::class);
    $firstCount = Currency::query()->count();

    $this->seed(CurrencySeeder::class);
    $secondCount = Currency::query()->count();

    expect($secondCount)->toBe($firstCount);
});

<?php

use App\Models\Store;

it('has fillable attributes', function () {
    $store = Store::factory()->create([
        'name' => 'London Warehouse',
        'city' => 'London',
        'country_code' => 'GB',
    ]);

    expect($store->name)->toBe('London Warehouse');
    expect($store->city)->toBe('London');
    expect($store->country_code)->toBe('GB');
});

it('casts is_default to boolean', function () {
    $store = Store::factory()->create(['is_default' => true]);

    expect($store->is_default)->toBeTrue();
});

it('scopes to default store', function () {
    Store::factory()->create(['is_default' => false, 'name' => 'Secondary']);
    Store::factory()->create(['is_default' => true, 'name' => 'Main']);

    $defaults = Store::query()->default()->get();

    expect($defaults)->toHaveCount(1);
    expect($defaults->first()->name)->toBe('Main');
});

<?php

use App\Enums\ShortageDispatchPolicy;
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

it('defaults is_virtual to false and casts it to boolean', function () {
    $store = Store::factory()->create();

    expect($store->is_virtual)->toBeFalse();

    $virtual = Store::factory()->virtual()->create();

    expect($virtual->fresh()->is_virtual)->toBeTrue();
});

it('casts operating_hours to an array', function () {
    $hours = [
        'monday' => ['open' => '09:00', 'close' => '17:00'],
        'sunday' => null,
    ];

    $store = Store::factory()->operatingHours($hours)->create();

    expect($store->fresh()->operating_hours)->toBe($hours);
});

it('leaves operating_hours null by default (24/7)', function () {
    $store = Store::factory()->create();

    expect($store->fresh()->operating_hours)->toBeNull();
});

it('defaults the shortage_dispatch_policy to warn_partial', function () {
    $store = Store::factory()->create();

    expect($store->fresh()->shortage_dispatch_policy)->toBe(ShortageDispatchPolicy::WarnPartial)
        ->and($store->dispatchPolicy())->toBe(ShortageDispatchPolicy::WarnPartial);
});

it('casts and exposes a configured shortage_dispatch_policy', function () {
    $store = Store::factory()->dispatchPolicy(ShortageDispatchPolicy::Block)->create();

    expect($store->fresh()->shortage_dispatch_policy)->toBe(ShortageDispatchPolicy::Block)
        ->and($store->dispatchPolicy())->toBe(ShortageDispatchPolicy::Block);
});

it('scopes to default store', function () {
    Store::factory()->create(['is_default' => false, 'name' => 'Secondary']);
    Store::factory()->create(['is_default' => true, 'name' => 'Main']);

    $defaults = Store::query()->default()->get();

    expect($defaults)->toHaveCount(1);
    expect($defaults->first()->name)->toBe('Main');
});

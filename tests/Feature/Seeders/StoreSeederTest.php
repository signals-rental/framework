<?php

use App\Models\Store;
use Database\Seeders\StoreSeeder;

it('seeds a default store', function () {
    $this->seed(StoreSeeder::class);

    expect(Store::query()->count())->toBe(1);
    expect(Store::default()->exists())->toBeTrue();
});

it('is idempotent and does not create duplicate default stores', function () {
    $this->seed(StoreSeeder::class);
    $this->seed(StoreSeeder::class);

    expect(Store::query()->count())->toBe(1);
});

it('does not create a default store when stores already exist', function () {
    Store::factory()->create(['name' => 'Existing', 'is_default' => false]);

    $this->seed(StoreSeeder::class);

    expect(Store::query()->count())->toBe(1);
    expect(Store::default()->exists())->toBeFalse();
});

<?php

use App\Actions\Products\CreateStockTransaction;
use App\Data\Products\CreateStockTransactionData;
use App\Enums\TransactionType;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('creates a stock transaction and updates quantity_held', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 0]);

    $dto = CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'transaction_type' => TransactionType::Buy->value,
        'quantity' => '10.0',
        'transaction_at' => now()->toISOString(),
    ]);

    $result = (new CreateStockTransaction)($dto);

    expect($result->transaction_type)->toBe(TransactionType::Buy->value);
    $stockLevel->refresh();
    expect((float) $stockLevel->quantity_held)->toBe(10.0);
});

it('decrements quantity for sell transactions', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 20]);

    $dto = CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'transaction_type' => TransactionType::Sell->value,
        'quantity' => '5.0',
        'transaction_at' => now()->toISOString(),
    ]);

    (new CreateStockTransaction)($dto);

    $stockLevel->refresh();
    expect((float) $stockLevel->quantity_held)->toBe(15.0);
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $stockLevel = StockLevel::factory()->create();

    $dto = CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'transaction_type' => TransactionType::Buy->value,
        'quantity' => '1.0',
        'transaction_at' => now()->toISOString(),
    ]);

    (new CreateStockTransaction)($dto);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);

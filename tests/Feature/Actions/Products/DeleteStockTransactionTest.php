<?php

use App\Actions\Products\DeleteStockTransaction;
use App\Events\AuditableEvent;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use App\Services\Api\WebhookService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('reverses a buy transaction effect on quantity_held when deleted', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 10]);

    // A buy transaction added 4 units; deleting it must subtract them back.
    $transaction = StockTransaction::factory()->buy()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'quantity' => '4.0',
    ]);

    (new DeleteStockTransaction)($transaction);

    $stockLevel->refresh();
    expect((float) $stockLevel->quantity_held)->toBe(6.0);

    $this->assertDatabaseMissing('stock_transactions', ['id' => $transaction->id]);
});

it('reverses a sell transaction effect on quantity_held when deleted', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 15]);

    // A sell transaction removed 5 units; deleting it must add them back.
    $transaction = StockTransaction::factory()->sell()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'quantity' => '5.0',
    ]);

    (new DeleteStockTransaction)($transaction);

    $stockLevel->refresh();
    expect((float) $stockLevel->quantity_held)->toBe(20.0);
});

it('throws an authorization exception without the stock.adjust permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $stockLevel = StockLevel::factory()->create(['quantity_held' => 10]);
    $transaction = StockTransaction::factory()->buy()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $stockLevel->store_id,
        'quantity' => '4.0',
    ]);

    (new DeleteStockTransaction)($transaction);
})->throws(AuthorizationException::class);

it('does not mutate quantity_held when authorization fails', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $stockLevel = StockLevel::factory()->create(['quantity_held' => 10]);
    $transaction = StockTransaction::factory()->buy()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $stockLevel->store_id,
        'quantity' => '4.0',
    ]);

    try {
        (new DeleteStockTransaction)($transaction);
    } catch (AuthorizationException) {
        // expected
    }

    $stockLevel->refresh();
    expect((float) $stockLevel->quantity_held)->toBe(10.0);
    $this->assertDatabaseHas('stock_transactions', ['id' => $transaction->id]);
});

it('fires the stock_transaction.deleted audit event', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 10]);
    $transaction = StockTransaction::factory()->buy()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'quantity' => '4.0',
    ]);

    (new DeleteStockTransaction)($transaction);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) use ($transaction) {
        return $event->action === 'stock_transaction.deleted'
            && $event->model->is($transaction);
    });
});

it('dispatches the stock_transaction.deleted webhook', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 10]);
    $transaction = StockTransaction::factory()->buy()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'quantity' => '4.0',
    ]);

    $this->mock(WebhookService::class)
        ->shouldReceive('dispatch')->once()
        ->with('stock_transaction.deleted', Mockery::type('array'));

    (new DeleteStockTransaction)($transaction);
});

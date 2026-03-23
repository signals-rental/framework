<?php

use App\Actions\Products\UpdateStockLevel;
use App\Data\Products\UpdateStockLevelData;
use App\Events\AuditableEvent;
use App\Models\StockLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('updates a stock level', function () {
    Event::fake([AuditableEvent::class]);

    $stockLevel = StockLevel::factory()->create(['quantity_held' => 5]);

    $data = UpdateStockLevelData::from([
        'quantity_held' => 20,
    ]);

    $result = (new UpdateStockLevel)($stockLevel, $data);

    expect($result->product_id)->toBe($stockLevel->product_id);
    expect((float) $result->quantity_held)->toBe(20.0);
    expect((float) $stockLevel->fresh()->quantity_held)->toBe(20.0);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'stock_level.updated';
    });
});

it('clears optional field to null when empty string is passed', function () {
    Event::fake([AuditableEvent::class]);

    $stockLevel = StockLevel::factory()->create(['location' => 'Warehouse A']);

    $data = UpdateStockLevelData::from(['location' => '']);
    (new UpdateStockLevel)($stockLevel, $data);

    expect($stockLevel->refresh()->location)->toBeNull();
});

it('leaves field unchanged when null is passed via DTO', function () {
    Event::fake([AuditableEvent::class]);

    $stockLevel = StockLevel::factory()->create(['location' => 'Warehouse A']);

    $data = UpdateStockLevelData::from(['item_name' => 'New Name']);
    (new UpdateStockLevel)($stockLevel, $data);

    expect($stockLevel->refresh()->location)->toBe('Warehouse A');
});

it('requires stock.adjust permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $stockLevel = StockLevel::factory()->create();

    $data = UpdateStockLevelData::from(['quantity_held' => 99]);

    (new UpdateStockLevel)($stockLevel, $data);
})->throws(AuthorizationException::class);

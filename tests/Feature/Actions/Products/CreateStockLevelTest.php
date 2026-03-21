<?php

use App\Actions\Products\CreateStockLevel;
use App\Data\Products\CreateStockLevelData;
use App\Data\Products\StockLevelData;
use App\Events\AuditableEvent;
use App\Models\Product;
use App\Models\Store;
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

it('creates a stock level', function () {
    Event::fake([AuditableEvent::class]);

    $product = Product::factory()->create();
    $store = Store::factory()->create();

    $data = CreateStockLevelData::from([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 10,
    ]);

    $result = (new CreateStockLevel)($data);

    expect($result)->toBeInstanceOf(StockLevelData::class)
        ->and($result->product_id)->toBe($product->id)
        ->and($result->store_id)->toBe($store->id);

    $this->assertDatabaseHas('stock_levels', [
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    Event::assertDispatched(AuditableEvent::class);
});

it('requires stock.adjust permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();
    $store = Store::factory()->create();

    $data = CreateStockLevelData::from([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    (new CreateStockLevel)($data);
})->throws(AuthorizationException::class);

<?php

use App\Actions\Products\DeleteStockLevel;
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

it('deletes a stock level', function () {
    Event::fake([AuditableEvent::class]);

    $stockLevel = StockLevel::factory()->create();
    $id = $stockLevel->id;

    (new DeleteStockLevel)($stockLevel);

    expect(StockLevel::find($id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('requires stock.adjust permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $stockLevel = StockLevel::factory()->create();

    (new DeleteStockLevel)($stockLevel);
})->throws(AuthorizationException::class);

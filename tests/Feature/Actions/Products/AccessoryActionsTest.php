<?php

use App\Actions\Products\CreateAccessory;
use App\Actions\Products\DeleteAccessory;
use App\Actions\Products\UpdateAccessory;
use App\Data\Products\CreateAccessoryData;
use App\Data\Products\UpdateAccessoryData;
use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Accessory;
use App\Models\Product;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates an accessory and fires AuditableEvent', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();

    $result = (new CreateAccessory)(CreateAccessoryData::from([
        'product_id' => $product->id,
        'accessory_product_id' => $accessoryProduct->id,
        'quantity' => '2.0',
    ]));

    expect($result->product_id)->toBe($product->id)
        ->and($result->accessory_product_id)->toBe($accessoryProduct->id);

    $this->assertDatabaseHas('accessories', [
        'product_id' => $product->id,
        'accessory_product_id' => $accessoryProduct->id,
    ]);

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e) => $e->action === 'accessory.created');
});

it('records an action_logs row when an accessory is created', function () {
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();

    $result = (new CreateAccessory)(CreateAccessoryData::from([
        'product_id' => $product->id,
        'accessory_product_id' => $accessoryProduct->id,
    ]));

    $this->assertDatabaseHas('action_logs', [
        'action' => 'accessory.created',
        'auditable_type' => Accessory::class,
        'auditable_id' => $result->id,
    ]);
});

it('dispatches the product.updated webhook when an accessory is created', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    Webhook::factory()->create([
        'events' => ['product.updated'],
        'is_active' => true,
    ]);

    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();

    (new CreateAccessory)(CreateAccessoryData::from([
        'product_id' => $product->id,
        'accessory_product_id' => $accessoryProduct->id,
    ]));

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'product.updated');
});

it('updates an accessory and fires AuditableEvent', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $accessory = Accessory::factory()->create();

    (new UpdateAccessory)($accessory, UpdateAccessoryData::from([
        'quantity' => '5.0',
    ]));

    expect((float) $accessory->fresh()->quantity)->toBe(5.0);

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e) => $e->action === 'accessory.updated');
});

it('deletes an accessory and fires AuditableEvent', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $accessory = Accessory::factory()->create();

    (new DeleteAccessory)($accessory);

    expect(Accessory::find($accessory->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e) => $e->action === 'accessory.deleted');
});

it('records an action_logs row when an accessory is deleted', function () {
    Queue::fake([DeliverWebhook::class]);

    $accessory = Accessory::factory()->create();

    (new DeleteAccessory)($accessory);

    $this->assertDatabaseHas('action_logs', [
        'action' => 'accessory.deleted',
        'auditable_type' => Accessory::class,
        'auditable_id' => $accessory->id,
    ]);
});

it('denies unauthorized users from creating an accessory', function () {
    $this->actingAs(User::factory()->create());

    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();

    (new CreateAccessory)(CreateAccessoryData::from([
        'product_id' => $product->id,
        'accessory_product_id' => $accessoryProduct->id,
    ]));
})->throws(AuthorizationException::class);

it('denies unauthorized users from deleting an accessory', function () {
    $this->actingAs(User::factory()->create());

    $accessory = Accessory::factory()->create();

    (new DeleteAccessory)($accessory);
})->throws(AuthorizationException::class);

it('rejects an archived (soft-deleted) product as an accessory', function () {
    $product = Product::factory()->create();
    $archived = Product::factory()->create();
    $archived->delete();

    expect(fn () => CreateAccessoryData::validate([
        'product_id' => $product->id,
        'accessory_product_id' => $archived->id,
    ]))->toThrow(ValidationException::class);
});

it('accepts a live product as an accessory', function () {
    $product = Product::factory()->create();
    $live = Product::factory()->create();

    $validated = CreateAccessoryData::validate([
        'product_id' => $product->id,
        'accessory_product_id' => $live->id,
    ]);

    expect($validated['accessory_product_id'])->toBe($live->id);
});

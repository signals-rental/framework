<?php

use App\Actions\Products\RestoreProduct;
use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Product;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('restores a soft-deleted product', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();
    $product->delete();

    expect(Product::find($product->id))->toBeNull();

    (new RestoreProduct)($product);

    $restored = Product::find($product->id);
    expect($restored)->not->toBeNull()
        ->and($restored->deleted_at)->toBeNull();
});

it('fires AuditableEvent with product.restored action', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();
    $product->delete();

    (new RestoreProduct)($product);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'product.restored';
    });
});

it('records an action_logs row when a product is restored', function () {
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();
    $product->delete();

    (new RestoreProduct)($product);

    $this->assertDatabaseHas('action_logs', [
        'action' => 'product.restored',
        'auditable_type' => Product::class,
        'auditable_id' => $product->id,
    ]);
});

it('dispatches webhook with product.restored event', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    Webhook::factory()->create([
        'events' => ['product.restored'],
        'is_active' => true,
    ]);

    $product = Product::factory()->create();
    $product->delete();

    (new RestoreProduct)($product);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === 'product.restored';
    });
});

it('does nothing when restoring a non-deleted product', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();

    (new RestoreProduct)($product);

    Event::assertNotDispatched(AuditableEvent::class);
    expect(Product::find($product->id))->not->toBeNull();
});

it('denies unauthorized users without products.delete permission', function () {
    $this->actingAs(User::factory()->create());

    $product = Product::factory()->create();
    $product->delete();

    (new RestoreProduct)($product);
})->throws(AuthorizationException::class);

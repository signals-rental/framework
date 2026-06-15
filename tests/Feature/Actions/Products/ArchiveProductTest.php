<?php

use App\Actions\Products\ArchiveProduct;
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

it('archives a product via soft delete', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();

    (new ArchiveProduct)($product);

    expect(Product::find($product->id))->toBeNull();
    expect(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('fires AuditableEvent with product.archived action', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();

    (new ArchiveProduct)($product);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'product.archived';
    });
});

it('records an action_logs row when a product is archived', function () {
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();

    (new ArchiveProduct)($product);

    $this->assertDatabaseHas('action_logs', [
        'action' => 'product.archived',
        'auditable_type' => Product::class,
        'auditable_id' => $product->id,
    ]);
});

it('dispatches webhook with product.archived event', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    Webhook::factory()->create([
        'events' => ['product.archived'],
        'is_active' => true,
    ]);

    $product = Product::factory()->create();

    (new ArchiveProduct)($product);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === 'product.archived';
    });
});

it('is idempotent for an already-archived product', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();
    $product->delete();

    (new ArchiveProduct)($product);

    Event::assertNotDispatched(AuditableEvent::class);
});

it('denies unauthorized users without products.delete permission', function () {
    $this->actingAs(User::factory()->create());

    $product = Product::factory()->create();

    (new ArchiveProduct)($product);
})->throws(AuthorizationException::class);

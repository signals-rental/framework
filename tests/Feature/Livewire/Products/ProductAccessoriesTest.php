<?php

use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Accessory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the accessories page with the add-accessory modal', function () {
    $product = Product::factory()->create(['name' => 'LED Panel']);

    $this->get(route('products.accessories', $product))
        ->assertOk()
        ->assertSee('Add Accessory');
});

it('links an accessory to the product', function () {
    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create(['name' => 'Power Cable']);

    Volt::test('products.accessories', ['product' => $product])
        ->set('selectedAccessoryId', $accessoryProduct->id)
        ->set('accessoryQuantity', 3)
        ->call('addAccessory')
        ->assertHasNoErrors()
        ->assertDispatched('close-modal');

    expect(Accessory::query()
        ->where('product_id', $product->id)
        ->where('accessory_product_id', $accessoryProduct->id)
        ->where('quantity', 3)
        ->exists())->toBeTrue();
});

it('resets the form fields after a successful add', function () {
    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();

    Volt::test('products.accessories', ['product' => $product])
        ->set('selectedAccessoryId', $accessoryProduct->id)
        ->set('accessoryQuantity', 5)
        ->call('addAccessory')
        ->assertSet('selectedAccessoryId', null)
        ->assertSet('accessoryQuantity', 1)
        ->assertSet('accessorySearch', '');
});

it('validates that an accessory is selected', function () {
    $product = Product::factory()->create();

    Volt::test('products.accessories', ['product' => $product])
        ->call('addAccessory')
        ->assertHasErrors(['selectedAccessoryId' => 'required']);
});

it('prevents a product being an accessory of itself', function () {
    $product = Product::factory()->create();

    Volt::test('products.accessories', ['product' => $product])
        ->set('selectedAccessoryId', $product->id)
        ->call('addAccessory')
        ->assertHasErrors('selectedAccessoryId');

    expect(Accessory::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

it('prevents linking the same accessory twice', function () {
    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();
    Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $accessoryProduct->id,
    ]);

    Volt::test('products.accessories', ['product' => $product])
        ->set('selectedAccessoryId', $accessoryProduct->id)
        ->call('addAccessory')
        ->assertHasErrors('selectedAccessoryId');
});

it('resets the form fields when resetForm is called', function () {
    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();

    // A single-character search avoids the (PostgreSQL-only) ilike query in the
    // searchResults computed, which is not executable on the SQLite test DB.
    Volt::test('products.accessories', ['product' => $product])
        ->set('selectedAccessoryId', $accessoryProduct->id)
        ->set('accessoryQuantity', 9)
        ->set('accessorySearch', 'x')
        ->call('resetForm')
        ->assertSet('selectedAccessoryId', null)
        ->assertSet('accessoryQuantity', 1)
        ->assertSet('accessorySearch', '');
});

it('removes an accessory via removeAccessory', function () {
    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();
    $accessory = Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $accessoryProduct->id,
    ]);

    Volt::test('products.accessories', ['product' => $product])
        ->call('removeAccessory', $accessory->id);

    expect(Accessory::find($accessory->id))->toBeNull();
});

it('routes add through the CreateAccessory action so the audit trail is written', function () {
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();

    Volt::test('products.accessories', ['product' => $product])
        ->set('selectedAccessoryId', $accessoryProduct->id)
        ->set('accessoryQuantity', 2)
        ->call('addAccessory')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('action_logs', [
        'action' => 'accessory.created',
        'auditable_type' => Accessory::class,
    ]);
});

it('routes remove through the DeleteAccessory action firing AuditableEvent', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $product = Product::factory()->create();
    $accessory = Accessory::factory()->create(['product_id' => $product->id]);

    Volt::test('products.accessories', ['product' => $product])
        ->call('removeAccessory', $accessory->id);

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e) => $e->action === 'accessory.deleted');
});

it('forbids adding an accessory for a user without products.edit permission', function () {
    $this->actingAs(User::factory()->create());

    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();

    Volt::test('products.accessories', ['product' => $product])
        ->set('selectedAccessoryId', $accessoryProduct->id)
        ->set('accessoryQuantity', 1)
        ->call('addAccessory')
        ->assertForbidden();

    expect(Accessory::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

it('forbids removing an accessory for a user without products.edit permission', function () {
    $this->actingAs(User::factory()->create());

    $product = Product::factory()->create();
    $accessory = Accessory::factory()->create(['product_id' => $product->id]);

    Volt::test('products.accessories', ['product' => $product])
        ->call('removeAccessory', $accessory->id)
        ->assertForbidden();

    expect(Accessory::find($accessory->id))->not->toBeNull();
});

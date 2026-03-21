<?php

use App\Actions\Products\UpdateProduct;
use App\Data\Products\UpdateProductData;
use App\Events\AuditableEvent;
use App\Models\Product;
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

it('updates a product', function () {
    Event::fake([AuditableEvent::class]);

    $product = Product::factory()->create(['name' => 'Old Name']);

    $data = UpdateProductData::from([
        'name' => 'New Name',
    ]);

    $result = (new UpdateProduct)($product, $data);

    expect($result->name)->toBe('New Name');
    expect($product->fresh()->name)->toBe('New Name');

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a product with custom fields', function () {
    Event::fake([AuditableEvent::class]);

    $customField = \App\Models\CustomField::factory()->create([
        'name' => 'product_ref',
        'module_type' => 'Product',
        'field_type' => \App\Enums\CustomFieldType::String,
    ]);

    $product = Product::factory()->create(['name' => 'Test Product']);

    $data = UpdateProductData::from([
        'name' => 'Updated Product',
        'custom_fields' => ['product_ref' => 'REF-999'],
    ]);

    $result = (new UpdateProduct)($product, $data);

    expect($result->name)->toBe('Updated Product');

    $cfv = \App\Models\CustomFieldValue::query()
        ->where('custom_field_id', $customField->id)
        ->where('entity_type', Product::class)
        ->where('entity_id', $product->id)
        ->first();

    expect($cfv)->not->toBeNull()
        ->and($cfv->value_string)->toBe('REF-999');
});

it('requires products.edit permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();

    $data = UpdateProductData::from(['name' => 'Hack']);

    (new UpdateProduct)($product, $data);
})->throws(AuthorizationException::class);

it('allows partial custom field updates without enforcing required', function () {
    Event::fake([AuditableEvent::class]);

    \App\Models\CustomField::factory()->string()->required()->create([
        'name' => 'mandatory_ref',
        'module_type' => 'Product',
    ]);
    \App\Models\CustomField::factory()->string()->create([
        'name' => 'optional_note',
        'module_type' => 'Product',
    ]);

    $product = Product::factory()->create();
    $product->syncCustomFields(['mandatory_ref' => 'REF-001']);

    $data = UpdateProductData::from([
        'custom_fields' => ['optional_note' => 'Updated note'],
    ]);

    $result = (new UpdateProduct)($product, $data);

    expect($result)->not->toBeNull();
});

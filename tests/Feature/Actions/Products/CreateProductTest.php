<?php

use App\Actions\Products\CreateProduct;
use App\Data\Products\CreateProductData;
use App\Data\Products\ProductData;
use App\Enums\ProductType;
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

it('creates a product', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreateProductData::from([
        'name' => 'Test Product',
        'product_type' => ProductType::Rental->value,
    ]);

    $result = (new CreateProduct)($data);

    expect($result)->toBeInstanceOf(ProductData::class)
        ->and($result->name)->toBe('Test Product')
        ->and($result->is_active)->toBeTrue();

    $this->assertDatabaseHas('products', ['name' => 'Test Product']);

    Event::assertDispatched(AuditableEvent::class);
});

it('creates a product with custom fields', function () {
    Event::fake([AuditableEvent::class]);

    \App\Models\CustomField::factory()->string()->create([
        'name' => 'product_ref',
        'module_type' => 'Product',
    ]);

    $data = CreateProductData::from([
        'name' => 'Custom Product',
        'product_type' => ProductType::Rental->value,
        'custom_fields' => ['product_ref' => 'REF-001'],
    ]);

    $result = (new CreateProduct)($data);

    expect($result->name)->toBe('Custom Product');

    $cfv = \App\Models\CustomFieldValue::query()
        ->where('entity_type', Product::class)
        ->where('entity_id', $result->id)
        ->first();

    expect($cfv)->not->toBeNull()
        ->and($cfv->value_string)->toBe('REF-001');
});

it('requires products.create permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $data = CreateProductData::from([
        'name' => 'Unauthorized Product',
        'product_type' => ProductType::Rental->value,
    ]);

    (new CreateProduct)($data);
})->throws(AuthorizationException::class);

it('rejects creation when required custom field is missing', function () {
    \App\Models\CustomField::factory()->string()->required()->create([
        'name' => 'mandatory_ref',
        'module_type' => 'Product',
    ]);

    $data = CreateProductData::from([
        'name' => 'Missing Required CF',
        'product_type' => ProductType::Rental->value,
        'custom_fields' => [],
    ]);

    (new CreateProduct)($data);
})->throws(\Illuminate\Validation\ValidationException::class);

it('does not persist product when required custom field validation fails', function () {
    \App\Models\CustomField::factory()->string()->required()->create([
        'name' => 'mandatory_ref',
        'module_type' => 'Product',
    ]);

    $data = CreateProductData::from([
        'name' => 'Orphan Product',
        'product_type' => ProductType::Rental->value,
        'custom_fields' => [],
    ]);

    try {
        (new CreateProduct)($data);
    } catch (\Illuminate\Validation\ValidationException) {
        // expected
    }

    expect(Product::where('name', 'Orphan Product')->exists())->toBeFalse();
});

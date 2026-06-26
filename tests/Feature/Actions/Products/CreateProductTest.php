<?php

use App\Actions\Products\CreateProduct;
use App\Data\Products\CreateProductData;
use App\Data\Products\ProductData;
use App\Enums\ProductType;
use App\Events\AuditableEvent;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

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

    CustomField::factory()->string()->create([
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

    $cfv = CustomFieldValue::query()
        ->where('entity_type', Product::class)
        ->where('entity_id', $result->id)
        ->first();

    expect($cfv)->not->toBeNull()
        ->and($cfv->value_string)->toBe('REF-001');
});

it('records an action_logs row when a product is created', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $result = (new CreateProduct)(CreateProductData::from([
        'name' => 'Audited Product',
        'product_type' => ProductType::Rental->value,
    ]));

    assertActionLogged('product.created', Product::class, $result->id, $user->id);
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
    CustomField::factory()->string()->required()->create([
        'name' => 'mandatory_ref',
        'module_type' => 'Product',
    ]);

    $data = CreateProductData::from([
        'name' => 'Missing Required CF',
        'product_type' => ProductType::Rental->value,
        'custom_fields' => [],
    ]);

    (new CreateProduct)($data);
})->throws(ValidationException::class);

it('rejects creation when the product name is empty', function () {
    expect(fn () => CreateProductData::validateAndCreate([
        'name' => '',
        'product_type' => ProductType::Rental->value,
    ]))->toThrow(ValidationException::class);

    expect(Product::query()->where('name', '')->exists())->toBeFalse();
});

it('does not persist product when required custom field validation fails', function () {
    CustomField::factory()->string()->required()->create([
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
    } catch (ValidationException) {
        // expected
    }

    expect(Product::where('name', 'Orphan Product')->exists())->toBeFalse();
});

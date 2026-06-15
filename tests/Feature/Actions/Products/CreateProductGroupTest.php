<?php

use App\Actions\Products\CreateProductGroup;
use App\Data\Products\CreateProductGroupData;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\ProductGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('creates a product group with valid data', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $dto = CreateProductGroupData::from([
        'name' => 'Lighting Equipment',
    ]);

    $result = (new CreateProductGroup)($dto);

    expect($result->name)->toBe('Lighting Equipment');
    expect(ProductGroup::count())->toBe(1);
});

it('creates a product group with description', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $dto = CreateProductGroupData::from([
        'name' => 'Sound',
        'description' => 'Audio equipment',
    ]);

    $result = (new CreateProductGroup)($dto);

    expect($result->name)->toBe('Sound');
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $dto = CreateProductGroupData::from(['name' => 'Test']);

    (new CreateProductGroup)($dto);
})->throws(AuthorizationException::class);

it('persists custom field values on create', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    CustomField::factory()->string()->create([
        'name' => 'colour_code',
        'module_type' => 'ProductGroup',
    ]);

    $result = (new CreateProductGroup)(CreateProductGroupData::from([
        'name' => 'Lighting',
        'custom_fields' => ['colour_code' => 'AMBER'],
    ]));

    $cfv = CustomFieldValue::query()
        ->where('entity_type', ProductGroup::class)
        ->where('entity_id', $result->id)
        ->first();

    expect($cfv)->not->toBeNull()
        ->and($cfv->value_string)->toBe('AMBER');
});

it('returns custom fields in the create response data', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    CustomField::factory()->string()->create([
        'name' => 'colour_code',
        'module_type' => 'ProductGroup',
    ]);

    $result = (new CreateProductGroup)(CreateProductGroupData::from([
        'name' => 'Lighting',
        'custom_fields' => ['colour_code' => 'AMBER'],
    ]));

    expect((array) $result->custom_fields)->toMatchArray(['colour_code' => 'AMBER']);
});

it('rejects creation when a required custom field is missing', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    CustomField::factory()->string()->required()->create([
        'name' => 'mandatory_code',
        'module_type' => 'ProductGroup',
    ]);

    (new CreateProductGroup)(CreateProductGroupData::from([
        'name' => 'Lighting',
        'custom_fields' => [],
    ]));
})->throws(ValidationException::class);

it('does not persist the group when required custom field validation fails', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    CustomField::factory()->string()->required()->create([
        'name' => 'mandatory_code',
        'module_type' => 'ProductGroup',
    ]);

    try {
        (new CreateProductGroup)(CreateProductGroupData::from([
            'name' => 'Orphan Group',
            'custom_fields' => [],
        ]));
    } catch (ValidationException) {
        // expected
    }

    expect(ProductGroup::where('name', 'Orphan Group')->exists())->toBeFalse();
});

<?php

use App\Actions\Products\MergeProduct;
use App\Data\Products\MergeProductData;
use App\Events\AuditableEvent;
use App\Models\Accessory;
use App\Models\Attachment;
use App\Models\Product;
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

it('merges two products of the same type', function () {
    Event::fake([AuditableEvent::class]);

    $primary = Product::factory()->rental()->create(['name' => 'Primary Product']);
    $secondary = Product::factory()->rental()->create(['name' => 'Secondary Product']);

    $result = (new MergeProduct)(MergeProductData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));

    expect($result->id)->toBe($primary->id)
        ->and($result->name)->toBe('Primary Product');

    // Secondary should be soft-deleted
    expect(Product::find($secondary->id))->toBeNull();
    expect(Product::withTrashed()->find($secondary->id))->not->toBeNull();
});

it('transfers stock levels to primary product', function () {
    Event::fake([AuditableEvent::class]);

    $primary = Product::factory()->rental()->create();
    $secondary = Product::factory()->rental()->create();
    StockLevel::factory()->count(3)->create(['product_id' => $secondary->id]);

    (new MergeProduct)(MergeProductData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));

    expect($primary->stockLevels()->count())->toBe(3);
    expect(StockLevel::where('product_id', $secondary->id)->count())->toBe(0);
});

it('transfers accessories to primary product skipping duplicates', function () {
    Event::fake([AuditableEvent::class]);

    $primary = Product::factory()->rental()->create();
    $secondary = Product::factory()->rental()->create();
    $sharedAccessory = Product::factory()->create();
    $uniqueAccessory = Product::factory()->create();

    Accessory::factory()->create([
        'product_id' => $primary->id,
        'accessory_product_id' => $sharedAccessory->id,
    ]);
    Accessory::factory()->create([
        'product_id' => $secondary->id,
        'accessory_product_id' => $sharedAccessory->id,
    ]);
    Accessory::factory()->create([
        'product_id' => $secondary->id,
        'accessory_product_id' => $uniqueAccessory->id,
    ]);

    (new MergeProduct)(MergeProductData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));

    // Primary should have shared + unique = 2 accessories
    expect($primary->accessories()->count())->toBe(2);
    // Secondary should have no accessories left
    expect(Accessory::where('product_id', $secondary->id)->count())->toBe(0);
});

it('transfers inverse accessories to primary product', function () {
    Event::fake([AuditableEvent::class]);

    $primary = Product::factory()->rental()->create();
    $secondary = Product::factory()->rental()->create();
    $parentProduct = Product::factory()->create();

    // parentProduct uses secondary as an accessory
    Accessory::factory()->create([
        'product_id' => $parentProduct->id,
        'accessory_product_id' => $secondary->id,
    ]);

    (new MergeProduct)(MergeProductData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));

    // The inverse accessory should now point to primary
    $inverseAccessory = Accessory::where('product_id', $parentProduct->id)->first();
    expect($inverseAccessory->accessory_product_id)->toBe($primary->id);
});

it('transfers attachments to primary product', function () {
    Event::fake([AuditableEvent::class]);

    $primary = Product::factory()->rental()->create();
    $secondary = Product::factory()->rental()->create();

    Attachment::factory()->create([
        'attachable_type' => Product::class,
        'attachable_id' => $secondary->id,
    ]);

    (new MergeProduct)(MergeProductData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));

    expect($primary->attachments()->count())->toBe(1);
    expect(Attachment::where('attachable_type', Product::class)
        ->where('attachable_id', $secondary->id)->count())->toBe(0);
});

it('soft-deletes the secondary product', function () {
    Event::fake([AuditableEvent::class]);

    $primary = Product::factory()->rental()->create();
    $secondary = Product::factory()->rental()->create();

    (new MergeProduct)(MergeProductData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));

    expect(Product::find($secondary->id))->toBeNull();
    expect(Product::withTrashed()->find($secondary->id)->deleted_at)->not->toBeNull();
});

it('throws exception when merging products of different types', function () {
    $primary = Product::factory()->rental()->create();
    $secondary = Product::factory()->sale()->create();

    (new MergeProduct)(MergeProductData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));
})->throws(\InvalidArgumentException::class, 'Cannot merge products of different types.');

it('requires authorization', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $primary = Product::factory()->rental()->create();
    $secondary = Product::factory()->rental()->create();

    (new MergeProduct)(MergeProductData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));
})->throws(AuthorizationException::class);

it('transfers unique custom field values and retains primary values', function () {
    Event::fake([AuditableEvent::class]);

    $primary = Product::factory()->rental()->create();
    $secondary = Product::factory()->rental()->create();

    // Create custom field definitions for Product entity type
    $sharedField = \App\Models\CustomField::factory()->string()->create([
        'name' => 'shared_ref',
        'module_type' => 'Product',
    ]);
    $uniqueField = \App\Models\CustomField::factory()->string()->create([
        'name' => 'unique_note',
        'module_type' => 'Product',
    ]);

    // Primary has a value for the shared field
    \App\Models\CustomFieldValue::factory()->create([
        'custom_field_id' => $sharedField->id,
        'entity_type' => Product::class,
        'entity_id' => $primary->id,
        'value_string' => 'Primary Ref',
    ]);

    // Secondary has a value for the shared field and the unique field
    \App\Models\CustomFieldValue::factory()->create([
        'custom_field_id' => $sharedField->id,
        'entity_type' => Product::class,
        'entity_id' => $secondary->id,
        'value_string' => 'Secondary Ref',
    ]);
    \App\Models\CustomFieldValue::factory()->create([
        'custom_field_id' => $uniqueField->id,
        'entity_type' => Product::class,
        'entity_id' => $secondary->id,
        'value_string' => 'Unique Note Value',
    ]);

    (new MergeProduct)(MergeProductData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));

    // Primary retains its original shared field value
    $primaryShared = \App\Models\CustomFieldValue::query()
        ->where('custom_field_id', $sharedField->id)
        ->where('entity_type', Product::class)
        ->where('entity_id', $primary->id)
        ->first();

    expect($primaryShared)->not->toBeNull()
        ->and($primaryShared->value_string)->toBe('Primary Ref');

    // Primary gains the secondary's unique field value
    $primaryUnique = \App\Models\CustomFieldValue::query()
        ->where('custom_field_id', $uniqueField->id)
        ->where('entity_type', Product::class)
        ->where('entity_id', $primary->id)
        ->first();

    expect($primaryUnique)->not->toBeNull()
        ->and($primaryUnique->value_string)->toBe('Unique Note Value');

    // Secondary should have no remaining custom field values
    $secondaryCfvCount = \App\Models\CustomFieldValue::query()
        ->where('entity_type', Product::class)
        ->where('entity_id', $secondary->id)
        ->count();

    expect($secondaryCfvCount)->toBe(0);
});

it('fires AuditableEvent with product.merged action and metadata', function () {
    Event::fake([AuditableEvent::class]);

    $primary = Product::factory()->rental()->create(['name' => 'Primary']);
    $secondary = Product::factory()->rental()->create(['name' => 'Secondary']);

    (new MergeProduct)(MergeProductData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) use ($primary, $secondary) {
        return $event->action === 'product.merged'
            && $event->model->getKey() === $primary->id
            && $event->metadata['secondary_id'] === $secondary->id
            && $event->metadata['secondary_name'] === 'Secondary';
    });
});

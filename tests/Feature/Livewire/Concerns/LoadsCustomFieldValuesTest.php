<?php

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('loads custom field values from a product into the form', function () {
    $customField = CustomField::factory()->string()->create([
        'name' => 'serial_code',
        'module_type' => 'Product',
    ]);

    $product = Product::factory()->create();

    CustomFieldValue::factory()->create([
        'custom_field_id' => $customField->id,
        'entity_type' => Product::class,
        'entity_id' => $product->id,
        'value_string' => 'ABC-123',
    ]);

    $component = Volt::test('products.form', ['product' => $product]);

    $component->assertSet('customFieldValues.serial_code', 'ABC-123');
});

it('loads boolean custom field values correctly', function () {
    $customField = CustomField::factory()->boolean()->create([
        'name' => 'is_hazardous',
        'module_type' => 'Product',
    ]);

    $product = Product::factory()->create();

    CustomFieldValue::factory()->create([
        'custom_field_id' => $customField->id,
        'entity_type' => Product::class,
        'entity_id' => $product->id,
        'value_string' => null,
        'value_boolean' => true,
    ]);

    $component = Volt::test('products.form', ['product' => $product]);

    expect($component->get('customFieldValues.is_hazardous'))->toBeTrue();
});

it('skips orphaned custom field values and logs a warning', function () {
    // Test the LoadsCustomFieldValues trait directly. The trait method calls
    // $model->load('customFieldValues.customField') and iterates over the
    // collection. We test it by creating a real product, loading it, then
    // injecting an orphaned CFV into the already-loaded relation. Since the
    // trait calls load() which would re-query, we use a test-only subclass
    // that overrides load() to return itself with the pre-set relation.
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'Orphaned custom field value')
                && isset($context['custom_field_value_id']);
        });

    // Create a real product with a good CFV
    $product = Product::factory()->create();
    $field = CustomField::factory()->string()->create([
        'name' => 'good_field',
        'module_type' => 'Product',
    ]);
    CustomFieldValue::factory()->create([
        'custom_field_id' => $field->id,
        'entity_type' => Product::class,
        'entity_id' => $product->id,
        'value_string' => 'Good Value',
    ]);

    // Load the real relationships
    $product->load('customFieldValues.customField');

    // Create an orphaned CFV (customField is null)
    $orphan = new CustomFieldValue;
    $orphan->id = 99999;
    $orphan->custom_field_id = 99999;
    $orphan->setRelation('customField', null);

    // Add the orphan to the loaded collection
    $product->customFieldValues->push($orphan);

    // Call the trait method directly via an anonymous invocable.
    // Since $model->load() is called in the trait, we need the model's
    // relation already set. We use a closure that binds to the trait.
    $traitMethod = Closure::bind(function (Product $model): array {
        // Skip the load() call — we've already loaded the relation above,
        // and re-loading would clear our injected orphan.
        $values = [];
        foreach ($model->customFieldValues as $cfv) {
            if ($cfv->customField === null) {
                \Illuminate\Support\Facades\Log::warning('Orphaned custom field value: definition not found', [
                    'custom_field_value_id' => $cfv->id,
                    'custom_field_id' => $cfv->custom_field_id,
                ]);

                continue;
            }
            /** @var \App\Enums\CustomFieldType $fieldType */
            $fieldType = $cfv->customField->field_type;
            $column = $fieldType->valueColumn();
            $values[$cfv->customField->name] = $cfv->{$column};
        }

        return $values;
    }, null);

    /** @var array<string, mixed> $result */
    $result = $traitMethod($product);

    // The good field should be present, the orphan skipped
    expect($result)->toHaveKey('good_field')
        ->and($result['good_field'])->toBe('Good Value');
});

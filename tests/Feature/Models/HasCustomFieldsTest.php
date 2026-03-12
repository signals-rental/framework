<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Store;

beforeEach(function () {
    // Create custom fields for the Store module type
    $this->textField = CustomField::factory()->create([
        'name' => 'region',
        'display_name' => 'Region',
        'module_type' => 'Store',
        'field_type' => CustomFieldType::Text,
    ]);

    $this->boolField = CustomField::factory()->boolean()->create([
        'name' => 'has_warehouse',
        'display_name' => 'Has Warehouse',
        'module_type' => 'Store',
    ]);

    $this->store = Store::factory()->create();
});

it('syncs custom fields from a flat array', function () {
    $this->store->syncCustomFields([
        'region' => 'North East',
        'has_warehouse' => true,
    ]);

    expect(CustomFieldValue::query()->count())->toBe(2);

    $textValue = CustomFieldValue::query()
        ->where('custom_field_id', $this->textField->id)
        ->first();

    expect($textValue->value_string)->toBe('North East')
        ->and($textValue->entity_type)->toBe(Store::class)
        ->and($textValue->entity_id)->toBe($this->store->id);

    $boolValue = CustomFieldValue::query()
        ->where('custom_field_id', $this->boolField->id)
        ->first();

    expect($boolValue->value_boolean)->toBeTrue();
});

it('updates existing custom field values on re-sync', function () {
    $this->store->syncCustomFields(['region' => 'North East']);
    $this->store->syncCustomFields(['region' => 'South West']);

    expect(CustomFieldValue::query()->count())->toBe(1);

    $value = CustomFieldValue::query()
        ->where('custom_field_id', $this->textField->id)
        ->first();

    expect($value->value_string)->toBe('South West');
});

it('ignores unknown field names during sync', function () {
    $this->store->syncCustomFields(['nonexistent_field' => 'value']);

    expect(CustomFieldValue::query()->count())->toBe(0);
});

it('ignores inactive field definitions during sync', function () {
    $this->textField->update(['is_active' => false]);

    $this->store->syncCustomFields(['region' => 'North East']);

    expect(CustomFieldValue::query()->count())->toBe(0);
});

it('gets custom fields as a flat array', function () {
    $this->store->syncCustomFields([
        'region' => 'North East',
        'has_warehouse' => true,
    ]);

    $customFields = $this->store->custom_fields;

    expect($customFields)->toBeArray()
        ->and($customFields['region'])->toBe('North East')
        ->and($customFields['has_warehouse'])->toBeTrue();
});

it('returns empty array when no custom fields exist', function () {
    expect($this->store->custom_fields)->toBe([]);
});

it('skips custom field values with missing field definition', function () {
    // Create a custom field value with a custom_field_id that has been deleted
    $this->store->syncCustomFields(['region' => 'North East']);

    // Delete the field definition but keep the value record
    $this->textField->forceDelete();

    // The accessor should skip the orphaned value
    $customFields = $this->store->fresh()->custom_fields;

    expect($customFields)->not->toHaveKey('region');
});

it('returns correct module type from class name', function () {
    expect($this->store->customFieldModuleType())->toBe('Store');
});

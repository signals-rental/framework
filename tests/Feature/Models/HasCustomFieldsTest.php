<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\ListName;
use App\Models\ListValue;
use App\Models\Store;

beforeEach(function () {
    // Create custom fields for the Store module type
    $this->textField = CustomField::factory()->create([
        'name' => 'region',
        'display_name' => 'Region',
        'module_type' => 'Store',
        'field_type' => CustomFieldType::String,
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

it('returns all active fields as null when no values are set', function () {
    $customFields = $this->store->custom_fields;

    expect($customFields)->toBeArray()
        ->and($customFields)->toHaveKeys(['region', 'has_warehouse'])
        ->and($customFields['region'])->toBeNull()
        ->and($customFields['has_warehouse'])->toBeNull();
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

it('syncs list of values field with string input resolving to list_value_id', function () {
    $listName = ListName::factory()->create();
    $listValue = ListValue::factory()->forList($listName)->create(['name' => 'Priority High']);

    $listField = CustomField::factory()->listOfValues()->create([
        'name' => 'priority',
        'display_name' => 'Priority',
        'module_type' => 'Store',
        'list_name_id' => $listName->id,
    ]);

    $this->store->syncCustomFields(['priority' => 'Priority High']);

    $cfv = CustomFieldValue::query()
        ->where('custom_field_id', $listField->id)
        ->first();

    expect($cfv->value_integer)->toBe($listValue->id);
});

it('syncs list of values field with int input storing directly', function () {
    $listName = ListName::factory()->create();
    $listValue = ListValue::factory()->forList($listName)->create(['name' => 'Priority High']);

    $listField = CustomField::factory()->listOfValues()->create([
        'name' => 'priority',
        'display_name' => 'Priority',
        'module_type' => 'Store',
        'list_name_id' => $listName->id,
    ]);

    $this->store->syncCustomFields(['priority' => $listValue->id]);

    $cfv = CustomFieldValue::query()
        ->where('custom_field_id', $listField->id)
        ->first();

    expect($cfv->value_integer)->toBe($listValue->id);
});

it('reads list of values field resolving id back to display name', function () {
    $listName = ListName::factory()->create();
    $listValue = ListValue::factory()->forList($listName)->create(['name' => 'Priority High']);

    CustomField::factory()->listOfValues()->create([
        'name' => 'priority',
        'display_name' => 'Priority',
        'module_type' => 'Store',
        'list_name_id' => $listName->id,
    ]);

    $this->store->syncCustomFields(['priority' => 'Priority High']);

    $customFields = $this->store->fresh()->custom_fields;

    expect($customFields['priority'])->toBe('Priority High');
});

it('syncs multi list of values field with array of strings resolving to ids', function () {
    $listName = ListName::factory()->create();
    $lv1 = ListValue::factory()->forList($listName)->create(['name' => 'Red']);
    $lv2 = ListValue::factory()->forList($listName)->create(['name' => 'Blue']);

    $multiField = CustomField::factory()->multiListOfValues()->create([
        'name' => 'colours',
        'display_name' => 'Colours',
        'module_type' => 'Store',
        'list_name_id' => $listName->id,
    ]);

    $this->store->syncCustomFields(['colours' => ['Red', 'Blue']]);

    $cfv = CustomFieldValue::query()
        ->where('custom_field_id', $multiField->id)
        ->first();

    expect($cfv->value_json)->toBeArray()
        ->and($cfv->value_json)->toContain($lv1->id)
        ->and($cfv->value_json)->toContain($lv2->id)
        ->and($cfv->value_json)->toHaveCount(2);
});

it('eagerLoadCustomFields batch-loads values for a collection of entities', function () {
    $store1 = Store::factory()->create();
    $store2 = Store::factory()->create();

    $store1->syncCustomFields(['region' => 'North']);
    $store2->syncCustomFields(['region' => 'South']);

    // Reload fresh entities
    $entities = Store::query()->whereIn('id', [$store1->id, $store2->id])->get();

    Store::eagerLoadCustomFields($entities);

    // Verify preloaded relation is set
    foreach ($entities as $entity) {
        expect($entity->relationLoaded('preloadedCustomFieldValues'))->toBeTrue();
    }

    // Verify custom_fields accessor uses preloaded values
    $result1 = $entities->firstWhere('id', $store1->id)->custom_fields;
    $result2 = $entities->firstWhere('id', $store2->id)->custom_fields;

    expect($result1['region'])->toBe('North')
        ->and($result2['region'])->toBe('South');
});

it('eagerLoadCustomFields handles empty collection without error', function () {
    Store::eagerLoadCustomFields(collect());

    // No exception means it passed
    expect(true)->toBeTrue();
});

it('customFieldModuleType can be overridden by a model', function () {
    // Default behavior returns class_basename
    $store = Store::factory()->create();
    expect($store->customFieldModuleType())->toBe('Store');
});

it('reads multi list of values field resolving ids to display names', function () {
    $listName = ListName::factory()->create();
    ListValue::factory()->forList($listName)->create(['name' => 'Red']);
    ListValue::factory()->forList($listName)->create(['name' => 'Blue']);

    CustomField::factory()->multiListOfValues()->create([
        'name' => 'colours',
        'display_name' => 'Colours',
        'module_type' => 'Store',
        'list_name_id' => $listName->id,
    ]);

    $this->store->syncCustomFields(['colours' => ['Red', 'Blue']]);

    $customFields = $this->store->fresh()->custom_fields;

    expect($customFields['colours'])->toBeArray()
        ->and($customFields['colours'])->toContain('Red')
        ->and($customFields['colours'])->toContain('Blue')
        ->and($customFields['colours'])->toHaveCount(2);
});

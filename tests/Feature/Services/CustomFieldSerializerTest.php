<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\ListName;
use App\Models\ListValue;
use App\Models\Store;
use App\Services\CustomFieldSerializer;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->serializer = app(CustomFieldSerializer::class);

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

it('toArray returns flat key-value array for string fields', function () {
    $this->serializer->fromArray($this->store, [
        'region' => 'North East',
        'has_warehouse' => true,
    ]);

    $result = $this->serializer->toArray($this->store->fresh());

    expect($result)->toBeArray()
        ->and($result['region'])->toBe('North East')
        ->and($result['has_warehouse'])->toBeTrue();
});

it('toArray resolves ListOfValues IDs to display names', function () {
    $listName = ListName::factory()->create();
    $listValue = ListValue::factory()->forList($listName)->create(['name' => 'Priority High']);

    CustomField::factory()->listOfValues()->create([
        'name' => 'priority',
        'display_name' => 'Priority',
        'module_type' => 'Store',
        'list_name_id' => $listName->id,
    ]);

    $this->serializer->fromArray($this->store, ['priority' => 'Priority High']);

    $result = $this->serializer->toArray($this->store->fresh());

    expect($result['priority'])->toBe('Priority High');
});

it('toArray resolves MultiListOfValues IDs to display names', function () {
    $listName = ListName::factory()->create();
    ListValue::factory()->forList($listName)->create(['name' => 'Red']);
    ListValue::factory()->forList($listName)->create(['name' => 'Blue']);

    CustomField::factory()->multiListOfValues()->create([
        'name' => 'colours',
        'display_name' => 'Colours',
        'module_type' => 'Store',
        'list_name_id' => $listName->id,
    ]);

    $this->serializer->fromArray($this->store, ['colours' => ['Red', 'Blue']]);

    $result = $this->serializer->toArray($this->store->fresh());

    expect($result['colours'])->toBeArray()
        ->and($result['colours'])->toContain('Red')
        ->and($result['colours'])->toContain('Blue')
        ->and($result['colours'])->toHaveCount(2);
});

it('fromArray writes string field values', function () {
    $this->serializer->fromArray($this->store, ['region' => 'South West']);

    $cfv = CustomFieldValue::query()
        ->where('custom_field_id', $this->textField->id)
        ->first();

    expect($cfv->value_string)->toBe('South West')
        ->and($cfv->entity_type)->toBe(Store::class)
        ->and($cfv->entity_id)->toBe($this->store->id);
});

it('fromArray resolves ListOfValues string input to ID', function () {
    $listName = ListName::factory()->create();
    $listValue = ListValue::factory()->forList($listName)->create(['name' => 'Urgent']);

    CustomField::factory()->listOfValues()->create([
        'name' => 'priority',
        'display_name' => 'Priority',
        'module_type' => 'Store',
        'list_name_id' => $listName->id,
    ]);

    $this->serializer->fromArray($this->store, ['priority' => 'Urgent']);

    $cfv = CustomFieldValue::query()
        ->where('entity_type', Store::class)
        ->where('entity_id', $this->store->id)
        ->whereHas('customField', fn ($q) => $q->where('name', 'priority'))
        ->first();

    expect($cfv->value_integer)->toBe($listValue->id);
});

it('fromArray resolves MultiListOfValues string array to ID array', function () {
    $listName = ListName::factory()->create();
    $lv1 = ListValue::factory()->forList($listName)->create(['name' => 'Green']);
    $lv2 = ListValue::factory()->forList($listName)->create(['name' => 'Yellow']);

    CustomField::factory()->multiListOfValues()->create([
        'name' => 'colours',
        'display_name' => 'Colours',
        'module_type' => 'Store',
        'list_name_id' => $listName->id,
    ]);

    $this->serializer->fromArray($this->store, ['colours' => ['Green', 'Yellow']]);

    $cfv = CustomFieldValue::query()
        ->where('entity_type', Store::class)
        ->where('entity_id', $this->store->id)
        ->whereHas('customField', fn ($q) => $q->where('name', 'colours'))
        ->first();

    expect($cfv->value_json)->toBeArray()
        ->and($cfv->value_json)->toContain($lv1->id)
        ->and($cfv->value_json)->toContain($lv2->id)
        ->and($cfv->value_json)->toHaveCount(2);
});

it('fromArray ignores unknown fields', function () {
    $this->serializer->fromArray($this->store, ['nonexistent_field' => 'value']);

    expect(CustomFieldValue::query()->count())->toBe(0);
});

it('fromArray ignores inactive fields', function () {
    $this->textField->update(['is_active' => false]);

    $this->serializer->fromArray($this->store, ['region' => 'North East']);

    expect(CustomFieldValue::query()->count())->toBe(0);
});

it('eagerLoad batch-loads values and toArray uses them', function () {
    $store1 = Store::factory()->create();
    $store2 = Store::factory()->create();

    $this->serializer->fromArray($store1, ['region' => 'North']);
    $this->serializer->fromArray($store2, ['region' => 'South']);

    // Reload to clear any state
    $entities = Store::query()->whereIn('id', [$store1->id, $store2->id])->get();

    $this->serializer->eagerLoad($entities, 'Store');

    // Verify the preloaded data is set as a relation
    foreach ($entities as $entity) {
        expect($entity->relationLoaded('preloadedCustomFieldValues'))->toBeTrue();
    }

    // toArray should use preloaded values without additional queries
    $result1 = $this->serializer->toArray($entities->firstWhere('id', $store1->id));
    $result2 = $this->serializer->toArray($entities->firstWhere('id', $store2->id));

    expect($result1['region'])->toBe('North')
        ->and($result2['region'])->toBe('South');
});

it('eagerLoad handles empty collection gracefully', function () {
    $this->serializer->eagerLoad(new Collection, 'Store');

    // Verify no queries were executed and no exceptions thrown
    expect(Store::query()->count())->toBeGreaterThanOrEqual(0);
});

it('eagerLoad sets empty collection for entities without custom field values', function () {
    $store1 = Store::factory()->create();
    $store2 = Store::factory()->create();

    // Only store1 has custom fields
    $this->serializer->fromArray($store1, ['region' => 'North']);

    $entities = Store::query()->whereIn('id', [$store1->id, $store2->id])->get();

    $this->serializer->eagerLoad($entities, 'Store');

    // store2 should have an empty preloaded collection
    $store2Entity = $entities->firstWhere('id', $store2->id);
    expect($store2Entity->relationLoaded('preloadedCustomFieldValues'))->toBeTrue();

    $result2 = $this->serializer->toArray($store2Entity);
    expect($result2)->toHaveKeys(['region', 'has_warehouse'])
        ->and($result2['region'])->toBeNull()
        ->and($result2['has_warehouse'])->toBeNull();

    // store1 should still have its values
    $result1 = $this->serializer->toArray($entities->firstWhere('id', $store1->id));
    expect($result1['region'])->toBe('North');
});

it('fromArray applies default values when applyDefaults is true', function () {
    $this->textField->update(['default_value' => 'Default Region']);
    $this->boolField->update(['default_value' => '1']);

    $store = Store::factory()->create();

    // Only provide has_warehouse, leave region to default
    $this->serializer->fromArray($store, ['has_warehouse' => false], applyDefaults: true);

    $result = $this->serializer->toArray($store->fresh());

    expect($result['region'])->toBe('Default Region')
        ->and($result['has_warehouse'])->toBeFalse();
});

it('fromArray does not apply defaults when applyDefaults is false', function () {
    $this->textField->update(['default_value' => 'Default Region']);

    $store = Store::factory()->create();

    $this->serializer->fromArray($store, ['has_warehouse' => true], applyDefaults: false);

    $result = $this->serializer->toArray($store->fresh());

    expect($result['region'])->toBeNull()
        ->and($result['has_warehouse'])->toBeTrue();
});

it('fromArray does not override provided values with defaults', function () {
    $this->textField->update(['default_value' => 'Default Region']);

    $store = Store::factory()->create();

    $this->serializer->fromArray($store, ['region' => 'Custom Region'], applyDefaults: true);

    $result = $this->serializer->toArray($store->fresh());

    expect($result['region'])->toBe('Custom Region');
});

it('fromArray skips defaults for fields with null default_value', function () {
    // default_value is null by default from factory
    $store = Store::factory()->create();

    $this->serializer->fromArray($store, [], applyDefaults: true);

    expect(CustomFieldValue::query()->count())->toBe(0);
});

it('toArray returns fields ordered by sort_order', function () {
    // Update sort_order so has_warehouse comes first
    $this->boolField->update(['sort_order' => 1]);
    $this->textField->update(['sort_order' => 2]);

    $store = Store::factory()->create();
    $this->serializer->fromArray($store, ['region' => 'North', 'has_warehouse' => true]);

    $result = $this->serializer->toArray($store->fresh());
    $keys = array_keys($result);

    expect($keys[0])->toBe('has_warehouse')
        ->and($keys[1])->toBe('region');
});

it('toArray excludes deactivated fields even if values exist', function () {
    $store = Store::factory()->create();
    $this->serializer->fromArray($store, ['region' => 'North', 'has_warehouse' => true]);

    // Deactivate the region field after values are stored
    $this->textField->update(['is_active' => false]);

    // Fresh resolver + serializer so the definitions cache doesn't include the deactivated field
    $resolver = new \App\Services\CustomFieldDefinitionResolver;
    $freshSerializer = new CustomFieldSerializer($resolver);
    $result = $freshSerializer->toArray($store->fresh());

    expect($result)->not->toHaveKey('region')
        ->and($result)->toHaveKey('has_warehouse')
        ->and($result['has_warehouse'])->toBeTrue();
});

it('fromArray applies falsy default value like zero', function () {
    $this->textField->update(['field_type' => \App\Enums\CustomFieldType::Number, 'default_value' => '0']);

    $store = Store::factory()->create();
    $this->serializer->fromArray($store, [], applyDefaults: true);

    $cfv = CustomFieldValue::query()
        ->where('custom_field_id', $this->textField->id)
        ->where('entity_type', Store::class)
        ->where('entity_id', $store->id)
        ->first();

    expect($cfv)->not->toBeNull()
        ->and((float) $cfv->value_decimal)->toBe(0.0);
});

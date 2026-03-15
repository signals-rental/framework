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

    // No exception means it passed
    expect(true)->toBeTrue();
});

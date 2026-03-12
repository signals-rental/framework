<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Models\CustomFieldValue;
use App\Models\ListName;

it('creates a custom field group', function () {
    $group = CustomFieldGroup::factory()->create(['name' => 'Compliance']);

    expect($group->name)->toBe('Compliance')
        ->and($group->sort_order)->toBe(0);
});

it('creates a custom field group for a plugin', function () {
    $group = CustomFieldGroup::factory()->plugin('my-plugin')->create();

    expect($group->plugin_name)->toBe('my-plugin');
});

it('creates a custom field with text type', function () {
    $field = CustomField::factory()->create([
        'name' => 'po_reference',
        'display_name' => 'PO Reference',
        'module_type' => 'Invoice',
    ]);

    expect($field->name)->toBe('po_reference')
        ->and($field->field_type)->toBe(CustomFieldType::Text)
        ->and($field->module_type)->toBe('Invoice')
        ->and($field->is_active)->toBeTrue()
        ->and($field->is_searchable)->toBeTrue()
        ->and($field->is_required)->toBeFalse();
});

it('creates a boolean custom field', function () {
    $field = CustomField::factory()->boolean()->create();

    expect($field->field_type)->toBe(CustomFieldType::Boolean);
});

it('creates a select custom field with list name', function () {
    $listName = ListName::factory()->create();
    $field = CustomField::factory()->select()->create(['list_name_id' => $listName->id]);

    expect($field->field_type)->toBe(CustomFieldType::Select)
        ->and($field->listName->id)->toBe($listName->id);
});

it('creates a required custom field', function () {
    $field = CustomField::factory()->required()->create();

    expect($field->is_required)->toBeTrue();
});

it('creates an inactive custom field', function () {
    $field = CustomField::factory()->inactive()->create();

    expect($field->is_active)->toBeFalse();
});

it('scopes custom fields by module type', function () {
    CustomField::factory()->forModule('Invoice')->create();
    CustomField::factory()->forModule('Member')->create();
    CustomField::factory()->forModule('Invoice')->create();

    expect(CustomField::query()->forModule('Invoice')->count())->toBe(2)
        ->and(CustomField::query()->forModule('Member')->count())->toBe(1);
});

it('scopes custom fields by active status', function () {
    CustomField::factory()->create();
    CustomField::factory()->inactive()->create();

    expect(CustomField::query()->active()->count())->toBe(1);
});

it('enforces unique name per module_type', function () {
    CustomField::factory()->create(['name' => 'test_field', 'module_type' => 'Invoice']);
    CustomField::factory()->create(['name' => 'test_field', 'module_type' => 'Member']);

    expect(CustomField::query()->count())->toBe(2);

    // Same name + module_type should fail
    expect(fn () => CustomField::factory()->create(['name' => 'test_field', 'module_type' => 'Invoice']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('relates custom field to group', function () {
    $group = CustomFieldGroup::factory()->create();
    $field = CustomField::factory()->inGroup($group)->create();

    expect($field->group->id)->toBe($group->id)
        ->and($group->customFields)->toHaveCount(1);
});

it('creates a custom field value', function () {
    $field = CustomField::factory()->create();
    $value = CustomFieldValue::factory()->create([
        'custom_field_id' => $field->id,
        'entity_type' => 'Member',
        'entity_id' => 1,
        'value_string' => 'test-value',
    ]);

    expect($value->customField->id)->toBe($field->id)
        ->and($value->value_string)->toBe('test-value');
});

it('enforces unique custom field value per entity', function () {
    $field = CustomField::factory()->create();

    CustomFieldValue::factory()->create([
        'custom_field_id' => $field->id,
        'entity_type' => 'Member',
        'entity_id' => 1,
    ]);

    expect(fn () => CustomFieldValue::factory()->create([
        'custom_field_id' => $field->id,
        'entity_type' => 'Member',
        'entity_id' => 1,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('maps field types to correct value columns', function () {
    expect(CustomFieldType::Text->valueColumn())->toBe('value_string')
        ->and(CustomFieldType::TextArea->valueColumn())->toBe('value_text')
        ->and(CustomFieldType::Integer->valueColumn())->toBe('value_integer')
        ->and(CustomFieldType::Decimal->valueColumn())->toBe('value_decimal')
        ->and(CustomFieldType::Boolean->valueColumn())->toBe('value_boolean')
        ->and(CustomFieldType::Date->valueColumn())->toBe('value_date')
        ->and(CustomFieldType::DateTime->valueColumn())->toBe('value_datetime')
        ->and(CustomFieldType::Time->valueColumn())->toBe('value_time')
        ->and(CustomFieldType::MultiSelect->valueColumn())->toBe('value_json');
});

it('casts settings, validation_rules, and visibility_rules as arrays', function () {
    $field = CustomField::factory()->create([
        'settings' => ['max_length' => 100],
        'validation_rules' => ['min' => 1],
        'visibility_rules' => ['show_when' => 'status_eq_active'],
    ]);

    $field->refresh();

    expect($field->settings)->toBe(['max_length' => 100])
        ->and($field->validation_rules)->toBe(['min' => 1])
        ->and($field->visibility_rules)->toBe(['show_when' => 'status_eq_active']);
});

it('cascades delete from custom field to values', function () {
    $field = CustomField::factory()->create();
    CustomFieldValue::factory()->create(['custom_field_id' => $field->id]);

    expect(CustomFieldValue::query()->count())->toBe(1);

    $field->delete();

    expect(CustomFieldValue::query()->count())->toBe(0);
});

it('accesses list name relationship', function () {
    $listName = ListName::factory()->create(['name' => 'Phone Types']);
    $field = CustomField::factory()->select()->create(['list_name_id' => $listName->id]);

    $loadedField = CustomField::with('listName')->find($field->id);

    expect($loadedField->listName)->not->toBeNull()
        ->and($loadedField->listName->name)->toBe('Phone Types');
});

it('accesses custom field value entity via morphTo', function () {
    $store = \App\Models\Store::factory()->create();
    $field = CustomField::factory()->create(['module_type' => 'Store']);
    $value = CustomFieldValue::factory()->create([
        'custom_field_id' => $field->id,
        'entity_type' => \App\Models\Store::class,
        'entity_id' => $store->id,
        'value_string' => 'test',
    ]);

    /** @var CustomFieldValue $loaded */
    $loaded = CustomFieldValue::with('entity')->find($value->id);

    /** @var \App\Models\Store $entity */
    $entity = $loaded->entity;

    expect($entity)->not->toBeNull()
        ->and($entity->id)->toBe($store->id);
});

it('nullifies group_id when group is deleted', function () {
    $group = CustomFieldGroup::factory()->create();
    $field = CustomField::factory()->inGroup($group)->create();

    $group->delete();
    $field->refresh();

    expect($field->custom_field_group_id)->toBeNull();
});

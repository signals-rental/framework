<?php

use App\Models\CustomFieldMultiValue;
use App\Models\CustomFieldValue;
use App\Models\ListName;
use App\Models\ListValue;

it('creates a custom field multi value', function () {
    $multiValue = CustomFieldMultiValue::factory()->create();

    expect($multiValue)->toBeInstanceOf(CustomFieldMultiValue::class)
        ->and($multiValue->custom_field_value_id)->not->toBeNull()
        ->and($multiValue->list_value_id)->not->toBeNull();
});

it('belongs to a custom field value', function () {
    $cfv = CustomFieldValue::factory()->create();
    $multiValue = CustomFieldMultiValue::factory()->create([
        'custom_field_value_id' => $cfv->id,
    ]);

    expect($multiValue->customFieldValue->id)->toBe($cfv->id);
});

it('belongs to a list value', function () {
    $listValue = ListValue::factory()->create();
    $multiValue = CustomFieldMultiValue::factory()->create([
        'list_value_id' => $listValue->id,
    ]);

    expect($multiValue->listValue->id)->toBe($listValue->id);
});

it('enforces unique custom_field_value_id and list_value_id pair', function () {
    $multiValue = CustomFieldMultiValue::factory()->create();

    expect(fn () => CustomFieldMultiValue::factory()->create([
        'custom_field_value_id' => $multiValue->custom_field_value_id,
        'list_value_id' => $multiValue->list_value_id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('cascades delete from custom field value', function () {
    $cfv = CustomFieldValue::factory()->create();
    CustomFieldMultiValue::factory()->create(['custom_field_value_id' => $cfv->id]);
    CustomFieldMultiValue::factory()->create(['custom_field_value_id' => $cfv->id]);

    expect(CustomFieldMultiValue::query()->where('custom_field_value_id', $cfv->id)->count())->toBe(2);

    $cfv->delete();

    expect(CustomFieldMultiValue::query()->where('custom_field_value_id', $cfv->id)->count())->toBe(0);
});

it('cascades delete from list value', function () {
    $listValue = ListValue::factory()->create();
    CustomFieldMultiValue::factory()->create(['list_value_id' => $listValue->id]);

    expect(CustomFieldMultiValue::query()->where('list_value_id', $listValue->id)->count())->toBe(1);

    $listValue->delete();

    expect(CustomFieldMultiValue::query()->where('list_value_id', $listValue->id)->count())->toBe(0);
});

it('allows multiple list values for the same custom field value', function () {
    $listName = ListName::factory()->create();
    $cfv = CustomFieldValue::factory()->create();
    $lv1 = ListValue::factory()->forList($listName)->create();
    $lv2 = ListValue::factory()->forList($listName)->create();

    CustomFieldMultiValue::factory()->create([
        'custom_field_value_id' => $cfv->id,
        'list_value_id' => $lv1->id,
    ]);
    CustomFieldMultiValue::factory()->create([
        'custom_field_value_id' => $cfv->id,
        'list_value_id' => $lv2->id,
    ]);

    expect(CustomFieldMultiValue::query()->where('custom_field_value_id', $cfv->id)->count())->toBe(2);
});

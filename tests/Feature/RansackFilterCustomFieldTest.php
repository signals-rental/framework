<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Models\Member;
use App\Services\Api\RansackFilter;

beforeEach(function () {
    $this->filter = new RansackFilter;

    $this->group = CustomFieldGroup::create([
        'name' => 'Test Group',
        'module_type' => 'App\\Models\\Member',
        'sort_order' => 0,
    ]);
});

it('applies custom field filter by field name', function () {
    CustomField::create([
        'name' => 'po_reference',
        'label' => 'PO Reference',
        'module_type' => 'App\\Models\\Member',
        'field_type' => CustomFieldType::String,
        'custom_field_group_id' => $this->group->id,
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $query = $this->filter->apply(
        Member::query(),
        ['cf.po_reference_eq' => 'PO-123'],
        [],
        customFieldModule: 'App\\Models\\Member',
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('exists')
        ->toContain('custom_field_id')
        ->toContain('value_string');
});

it('applies custom field filter by numeric field ID', function () {
    $cf = CustomField::create([
        'name' => 'po_reference',
        'label' => 'PO Reference',
        'module_type' => 'App\\Models\\Member',
        'field_type' => CustomFieldType::String,
        'custom_field_group_id' => $this->group->id,
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $query = $this->filter->apply(
        Member::query(),
        ["cf.{$cf->id}_eq" => 'PO-123'],
        [],
        customFieldModule: 'App\\Models\\Member',
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('exists')
        ->toContain('custom_field_id');
});

it('ignores custom field filter when field does not exist', function () {
    $baseSql = Member::query()->toRawSql();

    $query = $this->filter->apply(
        Member::query(),
        ['cf.nonexistent_eq' => 'value'],
        [],
        customFieldModule: 'App\\Models\\Member',
    );

    expect($query->toRawSql())->toBe($baseSql);
});

it('applies custom field filter with cont predicate', function () {
    CustomField::create([
        'name' => 'notes',
        'label' => 'Notes',
        'module_type' => 'App\\Models\\Member',
        'field_type' => CustomFieldType::String,
        'custom_field_group_id' => $this->group->id,
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $query = $this->filter->apply(
        Member::query(),
        ['cf.notes_cont' => 'important'],
        [],
        customFieldModule: 'App\\Models\\Member',
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('exists')
        ->toContain('ilike')
        ->toContain('important');
});

it('uses correct value column for different field types', function () {
    CustomField::create([
        'name' => 'is_vip',
        'label' => 'Is VIP',
        'module_type' => 'App\\Models\\Member',
        'field_type' => CustomFieldType::Boolean,
        'custom_field_group_id' => $this->group->id,
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $query = $this->filter->apply(
        Member::query(),
        ['cf.is_vip_true' => '1'],
        [],
        customFieldModule: 'App\\Models\\Member',
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('exists')
        ->toContain('value_boolean');
});

it('ignores non-searchable custom fields', function () {
    CustomField::create([
        'name' => 'internal_notes',
        'module_type' => 'App\\Models\\Member',
        'field_type' => CustomFieldType::String,
        'custom_field_group_id' => $this->group->id,
        'sort_order' => 0,
        'is_active' => true,
        'is_searchable' => false,
    ]);

    $baseSql = Member::query()->toRawSql();

    $query = $this->filter->apply(
        Member::query(),
        ['cf.internal_notes_eq' => 'secret'],
        [],
        customFieldModule: 'App\\Models\\Member',
    );

    expect($query->toRawSql())->toBe($baseSql);
});

it('ignores inactive custom fields when filtering by name', function () {
    CustomField::create([
        'name' => 'retired_field',
        'module_type' => 'App\\Models\\Member',
        'field_type' => CustomFieldType::String,
        'custom_field_group_id' => $this->group->id,
        'sort_order' => 0,
        'is_active' => false,
        'is_searchable' => true,
    ]);

    $baseSql = Member::query()->toRawSql();

    $query = $this->filter->apply(
        Member::query(),
        ['cf.retired_field_eq' => 'value'],
        [],
        customFieldModule: 'App\\Models\\Member',
    );

    expect($query->toRawSql())->toBe($baseSql);
});

it('ignores inactive custom fields when filtering by ID', function () {
    $cf = CustomField::create([
        'name' => 'retired_field',
        'module_type' => 'App\\Models\\Member',
        'field_type' => CustomFieldType::String,
        'custom_field_group_id' => $this->group->id,
        'sort_order' => 0,
        'is_active' => false,
        'is_searchable' => true,
    ]);

    $baseSql = Member::query()->toRawSql();

    $query = $this->filter->apply(
        Member::query(),
        ["cf.{$cf->id}_eq" => 'value'],
        [],
        customFieldModule: 'App\\Models\\Member',
    );

    expect($query->toRawSql())->toBe($baseSql);
});

it('allows searchable active custom fields', function () {
    CustomField::create([
        'name' => 'searchable_field',
        'module_type' => 'App\\Models\\Member',
        'field_type' => CustomFieldType::String,
        'custom_field_group_id' => $this->group->id,
        'sort_order' => 0,
        'is_active' => true,
        'is_searchable' => true,
    ]);

    $baseSql = Member::query()->toRawSql();

    $query = $this->filter->apply(
        Member::query(),
        ['cf.searchable_field_eq' => 'value'],
        [],
        customFieldModule: 'App\\Models\\Member',
    );

    expect($query->toRawSql())->not->toBe($baseSql)
        ->and($query->toRawSql())->toContain('exists');
});

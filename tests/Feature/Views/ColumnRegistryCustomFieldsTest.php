<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Views\MemberColumnRegistry;

beforeEach(function () {
    $this->group = CustomFieldGroup::factory()->create();
});

it('includes custom fields with cf. prefix in allColumns', function () {
    CustomField::factory()->inGroup($this->group)->create([
        'module_type' => 'Member',
        'name' => 'po_reference',
        'display_name' => 'PO Reference',
        'field_type' => CustomFieldType::String,
        'is_active' => true,
    ]);

    $registry = new MemberColumnRegistry;
    $columns = $registry->allColumns();

    expect($columns)->toHaveKey('cf.po_reference');
    expect($columns['cf.po_reference']->label)->toBe('PO Reference');
    expect($columns['cf.po_reference']->type)->toBe('string');
});

it('maps custom field types to column types correctly', function () {
    CustomField::factory()->inGroup($this->group)->boolean()->create([
        'module_type' => 'Member',
        'name' => 'is_vip',
        'display_name' => 'VIP Status',
        'is_active' => true,
    ]);

    CustomField::factory()->inGroup($this->group)->currency()->create([
        'module_type' => 'Member',
        'name' => 'budget',
        'display_name' => 'Budget',
        'is_active' => true,
    ]);

    $registry = new MemberColumnRegistry;
    $columns = $registry->allColumns();

    expect($columns['cf.is_vip']->type)->toBe('boolean');
    expect($columns['cf.budget']->type)->toBe('money');
});

it('does not include inactive custom fields', function () {
    CustomField::factory()->inGroup($this->group)->inactive()->create([
        'module_type' => 'Member',
        'name' => 'inactive_field',
        'display_name' => 'Inactive',
    ]);

    $registry = new MemberColumnRegistry;
    $columns = $registry->allColumns();

    expect($columns)->not->toHaveKey('cf.inactive_field');
});

it('validates cf. prefixed columns as valid', function () {
    CustomField::factory()->inGroup($this->group)->create([
        'module_type' => 'Member',
        'name' => 'po_reference',
        'display_name' => 'PO Reference',
        'is_active' => true,
    ]);

    $registry = new MemberColumnRegistry;
    $invalid = $registry->validateColumns(['name', 'cf.po_reference']);

    expect($invalid)->toBe([]);
});

it('reports unknown cf. columns as invalid', function () {
    $registry = new MemberColumnRegistry;
    $invalid = $registry->validateColumns(['name', 'cf.nonexistent']);

    expect($invalid)->toBe(['cf.nonexistent']);
});

it('returns correct modelClass', function () {
    $registry = new MemberColumnRegistry;

    expect($registry->modelClass())->toBe(\App\Models\Member::class);
});

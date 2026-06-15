<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\User;
use App\Services\VisibilityRuleEvaluator;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the custom fields page', function () {
    $this->get(route('admin.settings.custom-fields'))
        ->assertOk()
        ->assertSee('Custom Fields');
});

it('lists custom fields', function () {
    CustomField::factory()->create(['display_name' => 'PO Reference']);
    CustomField::factory()->create(['display_name' => 'Account Number']);

    Volt::test('admin.settings.custom-fields')
        ->assertSee('PO Reference')
        ->assertSee('Account Number');
});

it('can filter by module type', function () {
    CustomField::factory()->forModule('Member')->create(['display_name' => 'Member Field']);
    CustomField::factory()->forModule('Opportunity')->create(['display_name' => 'Opportunity Field']);

    Volt::test('admin.settings.custom-fields')
        ->set('moduleFilter', 'Member')
        ->assertSee('Member Field')
        ->assertDontSee('Opportunity Field');
});

it('renders the create form', function () {
    $this->get(route('admin.settings.custom-fields.create'))
        ->assertOk()
        ->assertSee('Create Custom Field');
});

it('can create a field', function () {
    Volt::test('admin.settings.custom-field-form')
        ->set('name', 'po_reference')
        ->set('displayName', 'PO Reference')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->call('save');

    expect(CustomField::where('name', 'po_reference')->exists())->toBeTrue();
});

it('renders the edit form', function () {
    $field = CustomField::factory()->create();

    $this->get(route('admin.settings.custom-fields.edit', $field))
        ->assertOk()
        ->assertSee('Edit Custom Field');
});

it('can edit a field', function () {
    $field = CustomField::factory()->create(['name' => 'test_field', 'display_name' => 'Old Display']);

    Volt::test('admin.settings.custom-field-form', ['customField' => $field])
        ->assertSet('displayName', 'Old Display')
        ->set('displayName', 'New Display')
        ->call('save')
        ->assertHasNoErrors();

    expect($field->fresh()->display_name)->toBe('New Display');
});

it('validates required fields', function () {
    Volt::test('admin.settings.custom-field-form')
        ->set('name', '')
        ->set('moduleType', '')
        ->call('save')
        ->assertHasErrors(['name', 'module_type']);
});

it('can delete a field', function () {
    $field = CustomField::factory()->create(['name' => 'to_delete']);

    Volt::test('admin.settings.custom-fields')
        ->call('delete', $field->id);

    expect(CustomField::where('name', 'to_delete')->exists())->toBeFalse();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.custom-fields'))
        ->assertForbidden();
});

it('renders the Phase-2 module options in the create form', function () {
    Volt::test('admin.settings.custom-field-form')
        // Module-type values (stored in custom_fields.module_type)
        ->assertSeeHtml('value="Activity"')
        ->assertSeeHtml('value="ProductGroup"')
        ->assertSeeHtml('value="StockLevel"')
        ->assertSeeHtml('value="Member"')
        ->assertSeeHtml('value="Product"')
        ->assertSeeHtml('value="Store"')
        // Human-readable labels
        ->assertSee('Product Group')
        ->assertSee('Stock Level');
});

it('does not render phantom module options for non-existent models', function () {
    Volt::test('admin.settings.custom-field-form')
        ->assertDontSeeHtml('value="Opportunity"')
        ->assertDontSeeHtml('value="Invoice"');
});

it('can create a field for a previously-missing module', function () {
    Volt::test('admin.settings.custom-field-form')
        ->set('name', 'inspection_due')
        ->set('displayName', 'Inspection Due')
        ->set('moduleType', 'StockLevel')
        ->set('fieldType', CustomFieldType::Date->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomField::where('name', 'inspection_due')->where('module_type', 'StockLevel')->exists())->toBeTrue();
});

it('persists string validation rules in the shape the validator consumes', function () {
    Volt::test('admin.settings.custom-field-form')
        ->set('name', 'po_reference')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->set('validationRules.min_length', '3')
        ->set('validationRules.max_length', '20')
        ->set('validationRules.pattern', '/^PO-\d+$/')
        ->call('save')
        ->assertHasNoErrors();

    $field = CustomField::where('name', 'po_reference')->firstOrFail();

    expect($field->validation_rules)->toBe([
        'min_length' => 3,
        'max_length' => 20,
        'pattern' => '/^PO-\d+$/',
    ]);
});

it('persists numeric validation rules for number fields', function () {
    Volt::test('admin.settings.custom-field-form')
        ->set('name', 'weight_kg')
        ->set('moduleType', 'Product')
        ->set('fieldType', CustomFieldType::Number->value)
        ->set('validationRules.min', '0')
        ->set('validationRules.max', '1000')
        ->call('save')
        ->assertHasNoErrors();

    $field = CustomField::where('name', 'weight_kg')->firstOrFail();

    expect($field->validation_rules)->toBe([
        'min' => 0,
        'max' => 1000,
    ]);
});

it('only persists validation keys relevant to the field type', function () {
    // min_length/max_length/pattern apply to String, but min/max do not.
    Volt::test('admin.settings.custom-field-form')
        ->set('name', 'short_code')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->set('validationRules.max_length', '5')
        ->set('validationRules.min', '99') // not applicable to String — must be dropped
        ->call('save')
        ->assertHasNoErrors();

    $field = CustomField::where('name', 'short_code')->firstOrFail();

    expect($field->validation_rules)->toBe(['max_length' => 5]);
});

it('persists null validation rules when none are set', function () {
    Volt::test('admin.settings.custom-field-form')
        ->set('name', 'plain_field')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomField::where('name', 'plain_field')->firstOrFail()->validation_rules)->toBeNull();
});

it('persists visibility rules in the exact shape VisibilityRuleEvaluator consumes', function () {
    Volt::test('admin.settings.custom-field-form')
        ->set('name', 'vat_number')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->call('addVisibilityRule')
        ->set('visibilityRules.0.field', 'membership_type')
        ->set('visibilityRules.0.operator', 'eq')
        ->set('visibilityRules.0.value', 'Organisation')
        ->call('addVisibilityRule')
        ->set('visibilityRules.1.field', 'is_active')
        ->set('visibilityRules.1.operator', 'present')
        ->call('save')
        ->assertHasNoErrors();

    $field = CustomField::where('name', 'vat_number')->firstOrFail();

    expect($field->visibility_rules)->toBe([
        ['field' => 'membership_type', 'operator' => 'eq', 'value' => 'Organisation'],
        ['field' => 'is_active', 'operator' => 'present'],
    ]);

    // The persisted shape must be understood by the evaluator.
    $evaluator = app(VisibilityRuleEvaluator::class);
    expect($evaluator->evaluate($field->visibility_rules, ['membership_type' => 'Organisation', 'is_active' => true]))->toBeTrue();
    expect($evaluator->evaluate($field->visibility_rules, ['membership_type' => 'Contact', 'is_active' => true]))->toBeFalse();
});

it('splits in/not_in visibility rule values into arrays', function () {
    Volt::test('admin.settings.custom-field-form')
        ->set('name', 'region_field')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->call('addVisibilityRule')
        ->set('visibilityRules.0.field', 'region')
        ->set('visibilityRules.0.operator', 'in')
        ->set('visibilityRules.0.value', 'north, south , east')
        ->call('save')
        ->assertHasNoErrors();

    $field = CustomField::where('name', 'region_field')->firstOrFail();

    expect($field->visibility_rules)->toBe([
        ['field' => 'region', 'operator' => 'in', 'value' => ['north', 'south', 'east']],
    ]);
});

it('drops visibility rule rows with a blank field', function () {
    Volt::test('admin.settings.custom-field-form')
        ->set('name', 'blank_rule_field')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->call('addVisibilityRule')
        ->set('visibilityRules.0.field', '')
        ->set('visibilityRules.0.operator', 'eq')
        ->set('visibilityRules.0.value', 'ignored')
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomField::where('name', 'blank_rule_field')->firstOrFail()->visibility_rules)->toBeNull();
});

it('can remove a visibility rule row', function () {
    $component = Volt::test('admin.settings.custom-field-form')
        ->call('addVisibilityRule')
        ->set('visibilityRules.0.field', 'first')
        ->call('addVisibilityRule')
        ->set('visibilityRules.1.field', 'second')
        ->call('removeVisibilityRule', 0);

    $component->assertSet('visibilityRules.0.field', 'second');
    expect($component->get('visibilityRules'))->toHaveCount(1);
});

it('loads existing validation and visibility rules back into the edit form', function () {
    $field = CustomField::factory()->create([
        'name' => 'editable_field',
        'module_type' => 'Member',
        'field_type' => CustomFieldType::String,
        'validation_rules' => ['min_length' => 2, 'max_length' => 50, 'pattern' => '/^[A-Z]+$/'],
        'visibility_rules' => [
            ['field' => 'membership_type', 'operator' => 'eq', 'value' => 'Organisation'],
            ['field' => 'tags', 'operator' => 'in', 'value' => ['a', 'b']],
        ],
    ]);

    Volt::test('admin.settings.custom-field-form', ['customField' => $field])
        ->assertSet('validationRules.min_length', '2')
        ->assertSet('validationRules.max_length', '50')
        ->assertSet('validationRules.pattern', '/^[A-Z]+$/')
        ->assertSet('visibilityRules.0.field', 'membership_type')
        ->assertSet('visibilityRules.0.operator', 'eq')
        ->assertSet('visibilityRules.0.value', 'Organisation')
        ->assertSet('visibilityRules.1.field', 'tags')
        ->assertSet('visibilityRules.1.operator', 'in')
        ->assertSet('visibilityRules.1.value', 'a, b');
});

it('preserves rules round-trip when editing without changes', function () {
    $field = CustomField::factory()->create([
        'name' => 'roundtrip_field',
        'module_type' => 'Member',
        'field_type' => CustomFieldType::String,
        'validation_rules' => ['max_length' => 10],
        'visibility_rules' => [['field' => 'status', 'operator' => 'eq', 'value' => 'active']],
    ]);

    Volt::test('admin.settings.custom-field-form', ['customField' => $field])
        ->call('save')
        ->assertHasNoErrors();

    $field->refresh();

    expect($field->validation_rules)->toBe(['max_length' => 10]);
    expect($field->visibility_rules)->toBe([['field' => 'status', 'operator' => 'eq', 'value' => 'active']]);
});

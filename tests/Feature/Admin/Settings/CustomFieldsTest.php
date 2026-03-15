<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\User;
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

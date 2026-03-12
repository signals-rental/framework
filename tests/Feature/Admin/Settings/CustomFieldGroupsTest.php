<?php

use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the custom field groups page', function () {
    $this->get(route('admin.settings.custom-field-groups'))
        ->assertOk()
        ->assertSee('Custom Field Groups');
});

it('lists custom field groups', function () {
    CustomFieldGroup::factory()->create(['name' => 'General Info']);
    CustomFieldGroup::factory()->create(['name' => 'Billing Details']);

    Volt::test('admin.settings.custom-field-groups')
        ->assertSee('General Info')
        ->assertSee('Billing Details');
});

it('renders the create form', function () {
    $this->get(route('admin.settings.custom-field-groups.create'))
        ->assertOk()
        ->assertSee('Create Custom Field Group');
});

it('can create a group', function () {
    Volt::test('admin.settings.custom-field-group-form')
        ->set('name', 'Test Group')
        ->set('description', 'A test group')
        ->set('sortOrder', 5)
        ->call('save');

    expect(CustomFieldGroup::where('name', 'Test Group')->exists())->toBeTrue();
});

it('renders the edit form', function () {
    $group = CustomFieldGroup::factory()->create(['name' => 'Existing Group']);

    $this->get(route('admin.settings.custom-field-groups.edit', $group))
        ->assertOk()
        ->assertSee('Edit Custom Field Group');
});

it('can edit a group', function () {
    $group = CustomFieldGroup::factory()->create(['name' => 'Old Name']);

    Volt::test('admin.settings.custom-field-group-form', ['customFieldGroup' => $group])
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('save');

    expect($group->fresh()->name)->toBe('New Name');
});

it('validates required name', function () {
    Volt::test('admin.settings.custom-field-group-form')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('can delete a group without fields', function () {
    $group = CustomFieldGroup::factory()->create(['name' => 'Empty Group']);

    Volt::test('admin.settings.custom-field-groups')
        ->call('deleteGroup', $group->id);

    expect(CustomFieldGroup::where('name', 'Empty Group')->exists())->toBeFalse();
});

it('cannot delete a group with fields', function () {
    $group = CustomFieldGroup::factory()->create(['name' => 'Has Fields']);
    CustomField::factory()->inGroup($group)->create();

    Volt::test('admin.settings.custom-field-groups')
        ->call('deleteGroup', $group->id)
        ->assertHasErrors('delete');

    expect(CustomFieldGroup::where('name', 'Has Fields')->exists())->toBeTrue();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.custom-field-groups'))
        ->assertForbidden();
});

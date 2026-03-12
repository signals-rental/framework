<?php

use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Models\Member;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->member = Member::factory()->create(['name' => 'Test Member']);
});

it('renders the custom fields page', function () {
    $this->get("/members/{$this->member->id}/custom-fields")
        ->assertOk()
        ->assertSee('Custom Fields');
});

it('shows message when no custom fields are configured', function () {
    Volt::test('members.custom-fields', ['member' => $this->member])
        ->assertSee('No custom fields have been configured for members.');
});

it('shows custom field groups', function () {
    $group = CustomFieldGroup::factory()->create(['name' => 'Business Info']);
    CustomField::factory()->inGroup($group)->forModule('Member')->create([
        'display_name' => 'Company Number',
    ]);

    Volt::test('members.custom-fields', ['member' => $this->member])
        ->assertSee('Business Info')
        ->assertSee('Company Number');
});

it('shows fields under General when no group is assigned', function () {
    CustomField::factory()->forModule('Member')->create([
        'display_name' => 'Ungrouped Field',
        'custom_field_group_id' => null,
    ]);

    Volt::test('members.custom-fields', ['member' => $this->member])
        ->assertSee('General')
        ->assertSee('Ungrouped Field');
});

it('shows field values when set', function () {
    $field = CustomField::factory()->string()->forModule('Member')->create([
        'display_name' => 'PO Reference',
    ]);

    $this->member->customFieldValues()->create([
        'custom_field_id' => $field->id,
        'value_string' => 'PO-12345',
    ]);

    Volt::test('members.custom-fields', ['member' => $this->member])
        ->assertSee('PO Reference')
        ->assertSee('PO-12345');
});

it('shows Not set for empty fields', function () {
    CustomField::factory()->string()->forModule('Member')->create([
        'display_name' => 'Empty Field',
    ]);

    Volt::test('members.custom-fields', ['member' => $this->member])
        ->assertSee('Empty Field')
        ->assertSee('Not set');
});

it('shows boolean field as Yes when true', function () {
    $field = CustomField::factory()->boolean()->forModule('Member')->create([
        'display_name' => 'VIP Customer',
    ]);

    $this->member->customFieldValues()->create([
        'custom_field_id' => $field->id,
        'value_boolean' => true,
    ]);

    Volt::test('members.custom-fields', ['member' => $this->member])
        ->assertSee('VIP Customer')
        ->assertSee('Yes');
});

it('does not show inactive custom fields', function () {
    CustomField::factory()->inactive()->forModule('Member')->create([
        'display_name' => 'Hidden Field',
    ]);

    Volt::test('members.custom-fields', ['member' => $this->member])
        ->assertDontSee('Hidden Field');
});

it('requires authentication', function () {
    auth()->logout();
    $this->get("/members/{$this->member->id}/custom-fields")
        ->assertRedirect();
});

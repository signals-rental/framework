<?php

use App\Models\OrganisationTaxClass;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the organisation tax classes page', function () {
    $this->get(route('admin.settings.tax.organisation-tax-classes'))
        ->assertOk()
        ->assertSee('Organisation Tax Classes');
});

it('lists organisation tax classes', function () {
    OrganisationTaxClass::factory()->create(['name' => 'Domestic']);
    OrganisationTaxClass::factory()->create(['name' => 'EU Business']);

    Volt::test('admin.settings.tax.organisation-tax-classes')
        ->assertSee('Domestic')
        ->assertSee('EU Business');
});

it('renders the create form', function () {
    $this->get(route('admin.settings.tax.organisation-tax-classes.create'))
        ->assertOk()
        ->assertSee('Create Organisation Tax Class');
});

it('can create a tax class', function () {
    Volt::test('admin.settings.tax.organisation-tax-class-form')
        ->set('name', 'Non-EU')
        ->set('description', 'Non-EU organisations')
        ->call('save');

    expect(OrganisationTaxClass::where('name', 'Non-EU')->exists())->toBeTrue();
});

it('renders the edit form', function () {
    $taxClass = OrganisationTaxClass::factory()->create(['name' => 'Editable Class']);

    $this->get(route('admin.settings.tax.organisation-tax-classes.edit', $taxClass))
        ->assertOk()
        ->assertSee('Edit Organisation Tax Class');
});

it('can edit a tax class', function () {
    $taxClass = OrganisationTaxClass::factory()->create(['name' => 'Old Name']);

    Volt::test('admin.settings.tax.organisation-tax-class-form', ['organisationTaxClass' => $taxClass])
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('save');

    expect($taxClass->fresh()->name)->toBe('New Name');
});

it('can set default', function () {
    $taxClass1 = OrganisationTaxClass::factory()->default()->create(['name' => 'Class A']);
    $taxClass2 = OrganisationTaxClass::factory()->create(['name' => 'Class B']);

    Volt::test('admin.settings.tax.organisation-tax-classes')
        ->call('setDefault', $taxClass2->id);

    expect($taxClass1->fresh()->is_default)->toBeFalse();
    expect($taxClass2->fresh()->is_default)->toBeTrue();
});

it('cannot delete the default tax class', function () {
    $taxClass = OrganisationTaxClass::factory()->default()->create(['name' => 'Default Class']);

    Volt::test('admin.settings.tax.organisation-tax-classes')
        ->call('deleteTaxClass', $taxClass->id)
        ->assertHasErrors('deleteTaxClass');

    expect(OrganisationTaxClass::where('name', 'Default Class')->exists())->toBeTrue();
});

it('can delete a non-default tax class', function () {
    OrganisationTaxClass::factory()->default()->create();
    $taxClass = OrganisationTaxClass::factory()->create(['name' => 'To Delete']);

    Volt::test('admin.settings.tax.organisation-tax-classes')
        ->call('deleteTaxClass', $taxClass->id);

    expect(OrganisationTaxClass::where('name', 'To Delete')->exists())->toBeFalse();
});

it('validates required name', function () {
    Volt::test('admin.settings.tax.organisation-tax-class-form')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.tax.organisation-tax-classes'))
        ->assertForbidden();
});

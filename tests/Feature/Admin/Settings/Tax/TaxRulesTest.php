<?php

use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the tax rules page', function () {
    $this->get(route('admin.settings.tax.rules'))
        ->assertOk()
        ->assertSee('Tax Rules');
});

it('lists tax rules with related names', function () {
    $orgClass = OrganisationTaxClass::factory()->create(['name' => 'Standard Org']);
    $prodClass = ProductTaxClass::factory()->create(['name' => 'Standard Product']);
    $taxRate = TaxRate::factory()->create(['name' => 'VAT']);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $prodClass->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    Volt::test('admin.settings.tax.rules')
        ->assertSee('Standard Org')
        ->assertSee('Standard Product')
        ->assertSee('VAT');
});

it('renders the create form', function () {
    $this->get(route('admin.settings.tax.rules.create'))
        ->assertOk()
        ->assertSee('Create Tax Rule');
});

it('can create a tax rule', function () {
    $orgClass = OrganisationTaxClass::factory()->create();
    $prodClass = ProductTaxClass::factory()->create();
    $taxRate = TaxRate::factory()->create();

    Volt::test('admin.settings.tax.rule-form')
        ->set('organisationTaxClassId', $orgClass->id)
        ->set('productTaxClassId', $prodClass->id)
        ->set('taxRateId', $taxRate->id)
        ->set('priority', 1)
        ->call('save');

    expect(TaxRule::where('tax_rate_id', $taxRate->id)->exists())->toBeTrue();
});

it('renders the edit form', function () {
    $taxRule = TaxRule::factory()->create();

    $this->get(route('admin.settings.tax.rules.edit', $taxRule))
        ->assertOk()
        ->assertSee('Edit Tax Rule');
});

it('can edit a tax rule', function () {
    $taxRule = TaxRule::factory()->create(['priority' => 0]);

    Volt::test('admin.settings.tax.rule-form', ['taxRule' => $taxRule])
        ->set('priority', 5)
        ->call('save');

    expect($taxRule->fresh()->priority)->toBe(5);
});

it('can delete a tax rule', function () {
    $taxRule = TaxRule::factory()->create();

    Volt::test('admin.settings.tax.rules')
        ->call('deleteTaxRule', $taxRule->id);

    $this->assertDatabaseMissing('tax_rules', ['id' => $taxRule->id]);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.tax.rules'))
        ->assertForbidden();
});

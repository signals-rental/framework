<?php

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

it('renders the tax rates page', function () {
    $this->get(route('admin.settings.tax.rates'))
        ->assertOk()
        ->assertSee('Tax Rates');
});

it('lists tax rates', function () {
    TaxRate::factory()->create(['name' => 'Standard']);
    TaxRate::factory()->create(['name' => 'Reduced']);

    Volt::test('admin.settings.tax.rates')
        ->assertSee('Standard')
        ->assertSee('Reduced');
});

it('renders the create form', function () {
    $this->get(route('admin.settings.tax.rates.create'))
        ->assertOk()
        ->assertSee('Create Tax Rate');
});

it('can create a tax rate', function () {
    Volt::test('admin.settings.tax.rate-form')
        ->set('name', 'Standard')
        ->set('rate', '20.00')
        ->call('save');

    expect(TaxRate::where('name', 'Standard')->exists())->toBeTrue();
});

it('renders the edit form', function () {
    $taxRate = TaxRate::factory()->create(['name' => 'Editable']);

    $this->get(route('admin.settings.tax.rates.edit', $taxRate))
        ->assertOk()
        ->assertSee('Edit Tax Rate');
});

it('can edit a tax rate', function () {
    $taxRate = TaxRate::factory()->create(['name' => 'Old Name']);

    Volt::test('admin.settings.tax.rate-form', ['taxRate' => $taxRate])
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('save');

    expect($taxRate->fresh()->name)->toBe('New Name');
});

it('can delete a tax rate', function () {
    $taxRate = TaxRate::factory()->create(['name' => 'To Delete']);

    Volt::test('admin.settings.tax.rates')
        ->call('deleteTaxRate', $taxRate->id);

    expect(TaxRate::where('name', 'To Delete')->exists())->toBeFalse();
});

it('cannot delete a tax rate used by rules', function () {
    $taxRate = TaxRate::factory()->create(['name' => 'In Use']);
    TaxRule::factory()->create(['tax_rate_id' => $taxRate->id]);

    Volt::test('admin.settings.tax.rates')
        ->call('deleteTaxRate', $taxRate->id)
        ->assertHasErrors('deleteTaxRate');

    expect(TaxRate::where('name', 'In Use')->exists())->toBeTrue();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.tax.rates'))
        ->assertForbidden();
});

<?php

use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the company settings page', function () {
    $this->get(route('admin.settings.company'))
        ->assertOk()
        ->assertSee('Company Details');
});

it('loads current settings values', function () {
    settings()->setMany([
        'company.name' => 'Test Company',
        'company.country_code' => 'GB',
        'company.timezone' => 'Europe/London',
        'company.currency' => 'GBP',
    ]);

    Volt::test('admin.settings.company')
        ->assertSet('name', 'Test Company')
        ->assertSet('countryCode', 'GB')
        ->assertSet('timezone', 'Europe/London')
        ->assertSet('currency', 'GBP');
});

it('saves company settings', function () {
    Volt::test('admin.settings.company')
        ->set('name', 'Updated Company')
        ->set('countryCode', 'US')
        ->set('timezone', 'America/New_York')
        ->set('currency', 'USD')
        ->set('taxRate', '8.25')
        ->set('taxLabel', 'Sales Tax')
        ->set('dateFormat', 'm/d/Y')
        ->set('timeFormat', 'g:i A')
        ->set('fiscalYearStart', 1)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('company-settings-saved');

    expect(settings('company.name'))->toBe('Updated Company');
    expect(settings('company.country_code'))->toBe('US');
    expect(settings('company.currency'))->toBe('USD');
});

it('validates required fields', function () {
    Volt::test('admin.settings.company')
        ->set('name', '')
        ->set('countryCode', '')
        ->call('save')
        ->assertHasErrors(['name', 'countryCode']);
});

it('validates country code is 2 characters', function () {
    Volt::test('admin.settings.company')
        ->set('name', 'Test')
        ->set('countryCode', 'GBR')
        ->set('timezone', 'Europe/London')
        ->set('currency', 'GBP')
        ->set('taxRate', '20')
        ->set('taxLabel', 'VAT')
        ->set('dateFormat', 'd/m/Y')
        ->set('timeFormat', 'H:i')
        ->call('save')
        ->assertHasErrors(['countryCode']);
});

it('auto-fills defaults when country changes', function () {
    Volt::test('admin.settings.company')
        ->set('countryCode', 'GB')
        ->assertSet('timezone', 'Europe/London')
        ->assertSet('currency', 'GBP')
        ->assertSet('taxRate', '20.00')
        ->assertSet('taxLabel', 'VAT');
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.company'))
        ->assertForbidden();
});

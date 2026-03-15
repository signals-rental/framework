<?php

use App\Actions\Setup\CheckInfrastructure;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Livewire\Volt\Volt;

beforeEach(function () {
    $envPath = base_path('.env');
    $this->envExisted = file_exists($envPath);
    if ($this->envExisted) {
        $this->originalEnv = file_get_contents($envPath);
    }

    config(['signals.installed' => true, 'signals.setup_complete' => false]);

    $this->mock(CheckInfrastructure::class, function ($mock) {
        $mock->shouldReceive('__invoke')->andReturn([
            'passed' => true,
            'checks' => [
                'database' => ['passed' => true, 'message' => 'Connected'],
                'migrations' => ['passed' => true, 'message' => '5 required tables found'],
                'redis' => ['passed' => true, 'message' => 'Connected'],
                'reverb' => ['passed' => true, 'message' => 'Configured'],
            ],
        ]);
    });
});

afterEach(function () {
    $envPath = base_path('.env');
    if ($this->envExisted) {
        file_put_contents($envPath, $this->originalEnv);
    } elseif (file_exists($envPath)) {
        unlink($envPath);
    }

    Artisan::call('config:clear');
});

it('renders the setup wizard on step 1', function () {
    $this->get('/setup')
        ->assertOk()
        ->assertSee('Company Details');
});

it('displays all country options', function () {
    Volt::test('setup.wizard')
        ->assertSee('United Kingdom')
        ->assertSee('United States');
});

it('auto-fills defaults when country is selected', function () {
    Volt::test('setup.wizard')
        ->set('countryCode', 'GB')
        ->assertSet('timezone', 'Europe/London')
        ->assertSet('currency', 'GBP')
        ->assertSet('taxRate', '20.00')
        ->assertSet('taxLabel', 'VAT')
        ->assertSet('dateFormat', 'd/m/Y')
        ->assertSet('timeFormat', 'H:i');
});

it('auto-fills store country code when country is selected', function () {
    Volt::test('setup.wizard')
        ->set('countryCode', 'US')
        ->assertSet('stores.0.country_code', 'US');
});

it('navigates to step 2 after validating step 1', function () {
    Volt::test('setup.wizard')
        ->set('countryCode', 'GB')
        ->set('companyName', 'Test Co')
        ->set('timezone', 'Europe/London')
        ->set('currency', 'GBP')
        ->set('taxRate', '20.00')
        ->set('taxLabel', 'VAT')
        ->set('dateFormat', 'd/m/Y')
        ->set('timeFormat', 'H:i')
        ->set('fiscalYearStart', 1)
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->assertSee('Stores');
});

it('validates required fields on step 1', function () {
    Volt::test('setup.wizard')
        ->set('companyName', '')
        ->set('countryCode', '')
        ->call('nextStep')
        ->assertHasErrors(['companyName', 'countryCode'])
        ->assertSet('currentStep', 1);
});

it('can add and remove stores', function () {
    $component = Volt::test('setup.wizard');

    // Starts with 1 store from mount()
    expect($component->get('stores'))->toHaveCount(1);

    $component->call('addStore');
    expect($component->get('stores'))->toHaveCount(2);

    $component->call('removeStore', 1);
    expect($component->get('stores'))->toHaveCount(1);
});

it('prevents removing the last store', function () {
    $component = Volt::test('setup.wizard');

    expect($component->get('stores'))->toHaveCount(1);

    $component->call('removeStore', 0);
    expect($component->get('stores'))->toHaveCount(1);
});

it('reassigns default when default store is removed', function () {
    $component = Volt::test('setup.wizard')
        ->call('addStore')
        ->call('setDefaultStore', 1);

    expect($component->get('stores.1.is_default'))->toBeTrue();
    expect($component->get('stores.0.is_default'))->toBeFalse();

    // Remove the default store (index 1)
    $component->call('removeStore', 1);

    // First store should become default
    expect($component->get('stores.0.is_default'))->toBeTrue();
});

it('navigates backward', function () {
    Volt::test('setup.wizard')
        ->set('countryCode', 'GB')
        ->set('companyName', 'Test Co')
        ->set('timezone', 'Europe/London')
        ->set('currency', 'GBP')
        ->set('taxRate', '20.00')
        ->set('taxLabel', 'VAT')
        ->set('dateFormat', 'd/m/Y')
        ->set('timeFormat', 'H:i')
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->call('previousStep')
        ->assertSet('currentStep', 1);
});

it('does not navigate backward from step 1', function () {
    Volt::test('setup.wizard')
        ->call('previousStep')
        ->assertSet('currentStep', 1);
});

it('allows jumping back to completed steps', function () {
    Volt::test('setup.wizard')
        ->set('countryCode', 'GB')
        ->set('companyName', 'Test Co')
        ->set('timezone', 'Europe/London')
        ->set('currency', 'GBP')
        ->set('taxRate', '20.00')
        ->set('taxLabel', 'VAT')
        ->set('dateFormat', 'd/m/Y')
        ->set('timeFormat', 'H:i')
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->call('goToStep', 1)
        ->assertSet('currentStep', 1);
});

it('does not allow jumping forward to unvisited steps', function () {
    Volt::test('setup.wizard')
        ->call('goToStep', 3)
        ->assertSet('currentStep', 1);
});

it('validates store name on step 2', function () {
    Volt::test('setup.wizard')
        ->set('currentStep', 2)
        ->set('stores', [['name' => '', 'street' => '', 'city' => '', 'county' => '', 'postcode' => '', 'country_code' => 'GB', 'is_default' => true]])
        ->call('nextStep')
        ->assertHasErrors(['stores.0.name'])
        ->assertSet('currentStep', 2);
});

it('validates profile selection on step 3', function () {
    Volt::test('setup.wizard')
        ->set('currentStep', 3)
        ->set('profile', 'invalid_profile')
        ->call('nextStep')
        ->assertHasErrors(['profile'])
        ->assertSet('currentStep', 3);
});

it('validates branding colours on step 4', function () {
    Volt::test('setup.wizard')
        ->set('currentStep', 4)
        ->set('primaryColour', 'not-a-colour')
        ->call('nextStep')
        ->assertHasErrors(['primaryColour'])
        ->assertSet('currentStep', 4);
});

it('validates admin fields on step 5', function () {
    Volt::test('setup.wizard')
        ->set('currentStep', 5)
        ->set('adminName', '')
        ->set('adminEmail', 'not-an-email')
        ->set('adminPassword', 'short')
        ->set('adminPassword_confirmation', 'mismatch')
        ->call('nextStep')
        ->assertHasErrors(['adminName', 'adminEmail', 'adminPassword'])
        ->assertSet('currentStep', 5);
});

it('shows the review step with all data', function () {
    Volt::test('setup.wizard')
        ->set('currentStep', 6)
        ->set('companyName', 'Review Co')
        ->set('countryCode', 'GB')
        ->set('adminName', 'Jane')
        ->set('adminEmail', 'jane@test.com')
        ->assertSee('Review Co')
        ->assertSee('Jane')
        ->assertSee('jane@test.com');
});

it('completes setup and redirects to dashboard', function () {
    Volt::test('setup.wizard')
        ->set('currentStep', 6)
        ->set('countryCode', 'GB')
        ->set('companyName', 'Final Test Co')
        ->set('timezone', 'Europe/London')
        ->set('currency', 'GBP')
        ->set('taxRate', '20.00')
        ->set('taxLabel', 'VAT')
        ->set('dateFormat', 'd/m/Y')
        ->set('timeFormat', 'H:i')
        ->set('fiscalYearStart', 1)
        ->set('profile', 'general')
        ->set('stores', [['name' => 'Main', 'street' => '', 'city' => '', 'county' => '', 'postcode' => '', 'country_code' => 'GB', 'is_default' => true]])
        ->set('primaryColour', '#1e3a5f')
        ->set('accentColour', '#3b82f6')
        ->set('adminName', 'Admin User')
        ->set('adminEmail', 'admin@test.com')
        ->set('adminPassword', 'SecurePass123!')
        ->set('adminPassword_confirmation', 'SecurePass123!')
        ->call('completeSetup')
        ->assertRedirect(route('dashboard'));

    expect(User::where('email', 'admin@test.com')->exists())->toBeTrue()
        ->and(Store::where('name', 'Main')->exists())->toBeTrue()
        ->and(settings('company.name'))->toBe('Final Test Co')
        ->and(config('signals.setup_complete'))->toBeTrue();
});

it('displays profile options with descriptions', function () {
    Volt::test('setup.wizard')
        ->set('currentStep', 3)
        ->assertSee('Dry Hire')
        ->assertSee('Full Service')
        ->assertSee('General')
        ->assertSee('Minimal');
});

it('shows enabled modules for selected profile', function () {
    Volt::test('setup.wizard')
        ->set('currentStep', 3)
        ->set('profile', 'dry_hire')
        ->assertSee('Opportunities')
        ->assertSee('Products')
        ->assertSee('Stock');
});

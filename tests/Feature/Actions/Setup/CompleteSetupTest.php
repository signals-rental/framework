<?php

use App\Actions\Setup\CompleteSetup;
use App\Data\Setup\CompleteSetupData;
use App\Enums\FeatureProfile;

pest()->group('env-writing');
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Env;

afterEach(function () {
    // Restore SIGNALS_SETUP_COMPLETE to false after tests that call CompleteSetup
    Env::writeVariables(
        ['SIGNALS_SETUP_COMPLETE' => 'false'],
        app()->basePath('.env'),
        overwrite: true,
    );
});

function makeSetupData(array $overrides = []): CompleteSetupData
{
    return new CompleteSetupData(
        companyName: $overrides['companyName'] ?? 'Test Rentals',
        countryCode: $overrides['countryCode'] ?? 'GB',
        timezone: $overrides['timezone'] ?? 'Europe/London',
        currency: $overrides['currency'] ?? 'GBP',
        taxRate: $overrides['taxRate'] ?? '20.00',
        taxLabel: $overrides['taxLabel'] ?? 'VAT',
        dateFormat: $overrides['dateFormat'] ?? 'd/m/Y',
        timeFormat: $overrides['timeFormat'] ?? 'H:i',
        fiscalYearStart: $overrides['fiscalYearStart'] ?? 1,
        profile: $overrides['profile'] ?? FeatureProfile::General,
        stores: $overrides['stores'] ?? [
            ['name' => 'Main Warehouse', 'city' => 'London', 'country_code' => 'GB', 'is_default' => true],
        ],
        primaryColour: $overrides['primaryColour'] ?? '#1e3a5f',
        accentColour: $overrides['accentColour'] ?? '#3b82f6',
        logoPath: $overrides['logoPath'] ?? null,
        adminName: $overrides['adminName'] ?? 'Jane Smith',
        adminEmail: $overrides['adminEmail'] ?? 'jane@example.com',
        adminPassword: $overrides['adminPassword'] ?? 'password123',
    );
}

it('creates an admin user with owner privileges', function () {
    $data = makeSetupData();

    $user = (new CompleteSetup)($data);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('Jane Smith')
        ->and($user->email)->toBe('jane@example.com')
        ->and($user->is_owner)->toBeTrue()
        ->and($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('hashes the admin password via model cast', function () {
    $data = makeSetupData();

    $user = (new CompleteSetup)($data);

    // Password is hashed by the User model's 'hashed' cast
    expect($user->password)->not->toBe('password123')
        ->and(password_verify('password123', $user->password))->toBeTrue();
});

it('writes company settings', function () {
    $data = makeSetupData();

    (new CompleteSetup)($data);

    expect(settings('company.name'))->toBe('Test Rentals')
        ->and(settings('company.country_code'))->toBe('GB')
        ->and(settings('company.timezone'))->toBe('Europe/London')
        ->and(settings('company.currency'))->toBe('GBP')
        ->and(settings('company.tax_rate'))->toBe('20.00')
        ->and(settings('company.tax_label'))->toBe('VAT')
        ->and(settings('company.date_format'))->toBe('d/m/Y')
        ->and(settings('company.time_format'))->toBe('H:i')
        ->and(settings('company.fiscal_year_start'))->toBe(1);
});

it('writes module settings from profile', function () {
    $data = makeSetupData(['profile' => FeatureProfile::DryHire]);

    (new CompleteSetup)($data);

    expect(settings()->moduleEnabled('opportunities'))->toBeTrue()
        ->and(settings()->moduleEnabled('products'))->toBeTrue()
        ->and(settings()->moduleEnabled('stock'))->toBeTrue()
        ->and(settings()->moduleEnabled('crew'))->toBeFalse()
        ->and(settings()->moduleEnabled('services'))->toBeFalse()
        ->and(settings()->moduleEnabled('projects'))->toBeFalse();
});

it('writes branding settings', function () {
    $data = makeSetupData([
        'primaryColour' => '#ff0000',
        'accentColour' => '#00ff00',
        'logoPath' => 'branding/logo.png',
    ]);

    (new CompleteSetup)($data);

    expect(settings('branding.primary_colour'))->toBe('#ff0000')
        ->and(settings('branding.accent_colour'))->toBe('#00ff00')
        ->and(settings('branding.logo_path'))->toBe('branding/logo.png');
});

it('creates stores from provided data', function () {
    $data = makeSetupData([
        'stores' => [
            ['name' => 'London HQ', 'city' => 'London', 'country_code' => 'GB', 'is_default' => true],
            ['name' => 'Manchester', 'city' => 'Manchester', 'country_code' => 'GB', 'is_default' => false],
        ],
    ]);

    (new CompleteSetup)($data);

    expect(Store::count())->toBe(2)
        ->and(Store::where('is_default', true)->first()->name)->toBe('London HQ')
        ->and(Store::where('name', 'Manchester')->exists())->toBeTrue();
});

it('creates a default store from company name when no stores provided', function () {
    $data = makeSetupData(['stores' => []]);

    (new CompleteSetup)($data);

    expect(Store::count())->toBe(1)
        ->and(Store::first()->name)->toBe('Test Rentals')
        ->and(Store::first()->is_default)->toBeTrue()
        ->and(Store::first()->country_code)->toBe('GB');
});

it('records setup metadata', function () {
    $data = makeSetupData(['profile' => FeatureProfile::Minimal]);

    (new CompleteSetup)($data);

    expect(settings('setup.profile'))->toBe('minimal')
        ->and(settings('setup.completed_at'))->not->toBeNull();
});

it('marks setup as complete in config', function () {
    $data = makeSetupData();

    (new CompleteSetup)($data);

    expect(config('signals.setup_complete'))->toBeTrue();
});

it('writes SIGNALS_SETUP_COMPLETE to env file', function () {
    $data = makeSetupData();

    (new CompleteSetup)($data);

    $envContent = file_get_contents(app()->basePath('.env'));
    expect($envContent)->toContain('SIGNALS_SETUP_COMPLETE=true');
});

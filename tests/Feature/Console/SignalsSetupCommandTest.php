<?php

use App\Actions\Setup\CheckInfrastructure;
use App\Enums\FeatureProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

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

it('registers the signals:setup command', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('signals:setup');
});

it('has the correct description', function () {
    $command = Artisan::all()['signals:setup'];

    expect($command->getDescription())
        ->toBe('Configure your Signals application: company details, stores, and admin account');
});

it('fails preflight when signals is not installed', function () {
    config(['signals.installed' => false]);

    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Test Co',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertFailed();
});

it('fails preflight when setup is already complete without force', function () {
    config(['signals.setup_complete' => true]);

    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Test Co',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertFailed();
});

it('allows re-running setup with force flag when already complete', function () {
    config(['signals.setup_complete' => true]);

    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--force' => true,
        '--company-name' => 'Test Co',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--profile' => 'general',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@force.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    expect(User::where('email', 'admin@force.com')->exists())->toBeTrue();
});

it('completes setup in non-interactive mode with all required options', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme Rentals',
        '--country' => 'GB',
        '--store-name' => 'London Warehouse',
        '--profile' => 'full_service',
        '--admin-name' => 'Jane Smith',
        '--admin-email' => 'jane@acme.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    $user = User::where('email', 'jane@acme.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Jane Smith');
    expect($user->is_owner)->toBeTrue();
    expect($user->is_admin)->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
});

it('writes company settings from country defaults', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme Rentals',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    expect(settings('company.name'))->toBe('Acme Rentals');
    expect(settings('company.country_code'))->toBe('GB');
    expect(settings('company.timezone'))->toBe('Europe/London');
    expect(settings('company.currency'))->toBe('GBP');
    expect(settings('company.tax_rate'))->toBe('20.00');
    expect(settings('company.tax_label'))->toBe('VAT');
});

it('writes module settings from feature profile', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--profile' => 'dry_hire',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    $expectedModules = FeatureProfile::DryHire->modules();

    foreach ($expectedModules as $module => $enabled) {
        expect(settings("modules.{$module}"))->toBe($enabled);
    }
});

it('creates a store with the provided name', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'AU',
        '--store-name' => 'Sydney Depot',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    $store = Store::where('name', 'Sydney Depot')->first();
    expect($store)->not->toBeNull();
    expect($store->country_code)->toBe('AU');
    expect($store->is_default)->toBeTrue();
});

it('writes branding settings with custom colours', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--primary-colour' => '#ff0000',
        '--accent-colour' => '#00ff00',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    expect(settings('branding.primary_colour'))->toBe('#ff0000');
    expect(settings('branding.accent_colour'))->toBe('#00ff00');
});

it('uses default branding colours when not specified', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    expect(settings('branding.primary_colour'))->toBe('#1e3a5f');
    expect(settings('branding.accent_colour'))->toBe('#3b82f6');
});

it('defaults to general profile when none specified', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    expect(settings('setup.profile'))->toBe('general');
});

it('allows overriding country defaults for timezone and currency', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--timezone' => 'America/New_York',
        '--currency' => 'USD',
        '--tax-rate' => '8.50',
        '--tax-label' => 'Sales Tax',
        '--store-name' => 'HQ',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    expect(settings('company.timezone'))->toBe('America/New_York');
    expect(settings('company.currency'))->toBe('USD');
    expect(settings('company.tax_rate'))->toBe('8.50');
    expect(settings('company.tax_label'))->toBe('Sales Tax');
});

it('fails with invalid profile option', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--profile' => 'nonexistent',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertFailed();
});

it('fails with invalid primary colour', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--primary-colour' => 'not-a-hex',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertFailed();
});

it('fails when admin password is too short', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'short',
    ])->assertFailed();
});

it('fails in non-interactive mode without required company-name', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertFailed();
});

it('fails in non-interactive mode without required admin-password', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
    ])->assertFailed();
});

it('records setup metadata', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--profile' => 'minimal',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    expect(settings('setup.profile'))->toBe('minimal');
    expect(settings('setup.completed_at'))->not->toBeNull();
});

it('marks setup as complete in config', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@test.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    expect(config('signals.setup_complete'))->toBeTrue();
});

it('registers all non-interactive options', function (string $optionName) {
    $command = Artisan::all()['signals:setup'];
    $definition = $command->getDefinition();

    expect($definition->hasOption($optionName))->toBeTrue();
})->with([
    'company-name',
    'country',
    'timezone',
    'currency',
    'tax-rate',
    'tax-label',
    'date-format',
    'time-format',
    'fiscal-year-start',
    'store-name',
    'profile',
    'primary-colour',
    'accent-colour',
    'logo-path',
    'admin-name',
    'admin-email',
    'admin-password',
    'force',
]);

it('hashes the admin password correctly', function () {
    $this->artisan('signals:setup', [
        '--no-interaction' => true,
        '--company-name' => 'Acme',
        '--country' => 'GB',
        '--store-name' => 'HQ',
        '--admin-name' => 'Admin',
        '--admin-email' => 'hash@test.com',
        '--admin-password' => 'password123',
    ])->assertSuccessful();

    $user = User::where('email', 'hash@test.com')->first();
    expect(password_verify('password123', $user->password))->toBeTrue();
});

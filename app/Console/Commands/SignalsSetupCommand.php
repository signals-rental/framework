<?php

namespace App\Console\Commands;

use App\Actions\Setup\CheckInfrastructure;
use App\Actions\Setup\CompleteSetup;
use App\Console\Commands\Concerns\HasSignalsBranding;
use App\Data\Reference\CountryData;
use App\Data\Setup\CompleteSetupData;
use App\Enums\FeatureProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'signals:setup')]
class SignalsSetupCommand extends Command
{
    use HasSignalsBranding;

    protected $signature = 'signals:setup
                            {--force : Skip confirmation prompts}
                            {--company-name= : Company name}
                            {--country= : Country code (ISO 3166-1 alpha-2)}
                            {--timezone= : Timezone}
                            {--currency= : Currency code (ISO 4217)}
                            {--tax-rate= : Tax rate percentage}
                            {--tax-label= : Tax label (e.g. VAT, GST)}
                            {--date-format= : Date format (PHP date format)}
                            {--time-format= : Time format (PHP date format)}
                            {--fiscal-year-start= : Fiscal year start month (1-12)}
                            {--store-name= : Default store name}
                            {--profile= : Feature profile (dry_hire, full_service, crew, general, minimal)}
                            {--primary-colour= : Primary brand colour (hex)}
                            {--accent-colour= : Accent brand colour (hex)}
                            {--logo-path= : Path to logo file}
                            {--admin-name= : Admin user full name}
                            {--admin-email= : Admin user email}
                            {--admin-password= : Admin user password}';

    protected $description = 'Configure your Signals application: company details, stores, and admin account';

    private bool $interactive = true;

    public function handle(): int
    {
        $this->interactive = $this->input->isInteractive();

        if (! $this->preflight()) {
            return self::FAILURE;
        }

        if ($this->interactive) {
            $this->displayWelcomeBanner();
        }

        try {
            $company = $this->configureCompany();
            $stores = $this->configureStores($company['countryCode']);
            $profile = $this->selectProfile();
            $branding = $this->configureBranding();
            $admin = $this->createAdmin();

            if (! $this->reviewAndConfirm($company, $stores, $profile, $branding, $admin)) {
                $this->components->warn('Setup cancelled.');

                return self::FAILURE;
            }

            $logoPath = $this->processLogo($branding['logoPath']);

            $data = new CompleteSetupData(
                companyName: $company['companyName'],
                countryCode: $company['countryCode'],
                timezone: $company['timezone'],
                currency: $company['currency'],
                taxRate: $company['taxRate'],
                taxLabel: $company['taxLabel'],
                dateFormat: $company['dateFormat'],
                timeFormat: $company['timeFormat'],
                fiscalYearStart: $company['fiscalYearStart'],
                profile: $profile,
                stores: $stores,
                primaryColour: $branding['primaryColour'],
                accentColour: $branding['accentColour'],
                logoPath: $logoPath,
                adminName: $admin['name'],
                adminEmail: $admin['email'],
                adminPassword: $admin['password'],
            );

            (new CompleteSetup)($data);

            $this->newLine();
            $this->components->info('Setup complete!');
            $this->newLine();
            $this->line('  You can now log in at: '.config('app.url'));
            $this->line('  Email: '.$admin['email']);
            $this->newLine();
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function preflight(): bool
    {
        if (! config('signals.installed')) {
            $this->components->error('Signals is not installed. Run "php artisan signals:install" first.');

            return false;
        }

        if (config('signals.setup_complete')) {
            if (! $this->option('force')) {
                $this->components->error('Setup has already been completed. Use --force to re-run.');

                return false;
            }

            $this->components->warn('Re-running setup with --force. Existing settings will be overwritten.');
        }

        $result = app(CheckInfrastructure::class)();

        if (! $result['passed']) {
            $this->components->error('Infrastructure pre-flight checks failed:');

            foreach ($result['checks'] as $name => $check) {
                if (! $check['passed']) {
                    $this->components->twoColumnDetail(
                        "<fg=red>FAIL</> {$name}",
                        $check['message'],
                    );
                }
            }

            $this->newLine();
            $this->line('  Run "php artisan signals:install" to configure infrastructure, then re-run setup.');

            return false;
        }

        if ($this->interactive) {
            foreach ($result['checks'] as $name => $check) {
                $this->components->twoColumnDetail(
                    "<fg=green>PASS</> {$name}",
                    $check['message'],
                );
            }
            $this->newLine();
        }

        return true;
    }

    private function displayWelcomeBanner(): void
    {
        $this->displaySignalsLogo();

        $this->line('  <fg=white;options=bold>Signals</> — Setup Wizard');
        $this->line('  Configure your company, stores, and admin account.');
        $this->newLine();
    }

    /**
     * @return array{companyName: string, countryCode: string, timezone: string, currency: string, taxRate: string, taxLabel: string, dateFormat: string, timeFormat: string, fiscalYearStart: int}
     */
    private function configureCompany(): array
    {
        if ($this->interactive) {
            $this->components->twoColumnDetail('<fg=white;options=bold>Company Details</>', '');
            $this->newLine();
        }

        $countryOptions = CountryData::options();

        $countryCode = $this->optionOrSelect(
            'country',
            'Country',
            $countryOptions,
            'GB',
        );

        $defaults = CountryData::defaults($countryCode) ?? CountryData::defaults('GB');

        $companyName = $this->optionOrPrompt('company-name', 'Company Name', '');

        $timezone = $this->optionOrPrompt('timezone', 'Timezone', $defaults['timezone']);
        $currency = $this->optionOrPrompt('currency', 'Currency Code', $defaults['currency']);
        $taxRate = $this->optionOrPrompt('tax-rate', 'Tax Rate (%)', $defaults['tax_rate']);
        $taxLabel = $this->optionOrPrompt('tax-label', 'Tax Label', $defaults['tax_label']);
        $dateFormat = $this->optionOrPrompt('date-format', 'Date Format', $defaults['date_format']);
        $timeFormat = $this->optionOrPrompt('time-format', 'Time Format', $defaults['time_format']);

        $fiscalYearStart = (int) $this->optionOrPrompt(
            'fiscal-year-start',
            'Fiscal Year Start Month (1-12)',
            '1',
        );

        if ($fiscalYearStart < 1 || $fiscalYearStart > 12) {
            throw new RuntimeException('Fiscal year start must be between 1 and 12.');
        }

        return [
            'companyName' => $companyName,
            'countryCode' => $countryCode,
            'timezone' => $timezone,
            'currency' => $currency,
            'taxRate' => $taxRate,
            'taxLabel' => $taxLabel,
            'dateFormat' => $dateFormat,
            'timeFormat' => $timeFormat,
            'fiscalYearStart' => $fiscalYearStart,
        ];
    }

    /**
     * @return array<int, array{name: string, street: string, city: string, county: string, postcode: string, country_code: string, is_default: bool}>
     */
    private function configureStores(string $countryCode): array
    {
        if ($this->interactive) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=white;options=bold>Store Configuration</>', '');
            $this->newLine();
        }

        $storeName = $this->optionOrPrompt(
            'store-name',
            'Store Name',
            '',
        );

        $stores = [
            [
                'name' => $storeName,
                'street' => '',
                'city' => '',
                'county' => '',
                'postcode' => '',
                'country_code' => $countryCode,
                'is_default' => true,
            ],
        ];

        if ($this->interactive && ! $this->option('force')) {
            while (confirm('Add another store?', false)) {
                $name = text(label: 'Store Name', required: true);
                $stores[] = [
                    'name' => $name,
                    'street' => '',
                    'city' => '',
                    'county' => '',
                    'postcode' => '',
                    'country_code' => $countryCode,
                    'is_default' => false,
                ];
            }
        }

        return $stores;
    }

    private function selectProfile(): FeatureProfile
    {
        if ($this->interactive) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=white;options=bold>Feature Profile</>', '');
            $this->newLine();
        }

        $options = [];
        foreach (FeatureProfile::cases() as $profile) {
            $options[$profile->value] = $profile->label().' — '.$profile->description();
        }

        $value = $this->optionOrSelect(
            'profile',
            'Feature Profile',
            $options,
            FeatureProfile::General->value,
        );

        $profile = FeatureProfile::tryFrom($value);

        if (! $profile) {
            throw new RuntimeException(
                "Invalid profile '{$value}'. Allowed: ".implode(', ', array_column(FeatureProfile::cases(), 'value'))
            );
        }

        if ($this->interactive) {
            $modules = $profile->modules();
            $enabled = array_keys(array_filter($modules));
            $disabled = array_keys(array_filter($modules, fn ($v) => ! $v));

            $this->components->bulletList([
                '<fg=green>Enabled</>: '.implode(', ', $enabled),
                '<fg=gray>Disabled</>: '.implode(', ', $disabled),
            ]);
        }

        return $profile;
    }

    /**
     * @return array{primaryColour: string, accentColour: string, logoPath: string|null}
     */
    private function configureBranding(): array
    {
        if ($this->interactive) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=white;options=bold>Branding</>', '');
            $this->newLine();
        }

        $primaryColour = $this->optionOrPrompt(
            'primary-colour',
            'Primary Colour (hex)',
            '#1e3a5f',
            required: false,
        );

        $accentColour = $this->optionOrPrompt(
            'accent-colour',
            'Accent Colour (hex)',
            '#3b82f6',
            required: false,
        );

        $logoPath = $this->option('logo-path');

        if ($logoPath === null && $this->interactive) {
            $logoPath = text(
                label: 'Logo File Path (leave blank to skip)',
                default: '',
                required: false,
                hint: 'Local file path to PNG, JPG, or SVG',
            );
            $logoPath = $logoPath ?: null;
        }

        if ($primaryColour && ! preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColour)) {
            throw new RuntimeException("Invalid primary colour '{$primaryColour}'. Must be a hex colour (e.g. #1e3a5f).");
        }

        if ($accentColour && ! preg_match('/^#[0-9a-fA-F]{6}$/', $accentColour)) {
            throw new RuntimeException("Invalid accent colour '{$accentColour}'. Must be a hex colour (e.g. #3b82f6).");
        }

        return [
            'primaryColour' => $primaryColour ?: '#1e3a5f',
            'accentColour' => $accentColour ?: '#3b82f6',
            'logoPath' => $logoPath,
        ];
    }

    /**
     * @return array{name: string, email: string, password: string}
     */
    private function createAdmin(): array
    {
        if ($this->interactive) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=white;options=bold>Admin Account</>', '');
            $this->newLine();
        }

        $name = $this->optionOrPrompt('admin-name', 'Full Name', '');
        $email = $this->optionOrPrompt('admin-email', 'Email Address', '');

        $pass = $this->option('admin-password');
        if ($pass === null) {
            if (! $this->interactive) {
                throw new RuntimeException('The --admin-password option is required in non-interactive mode.');
            }

            $pass = password(label: 'Password', required: true, hint: 'Minimum 12 characters, mixed case, numbers, and symbols');
        }

        if (strlen($pass) < 12) {
            throw new RuntimeException('Password must be at least 12 characters.');
        }

        if (! preg_match('/[a-z]/', $pass) || ! preg_match('/[A-Z]/', $pass)
            || ! preg_match('/[0-9]/', $pass) || ! preg_match('/[\W_]/', $pass)) {
            throw new RuntimeException('Password must contain uppercase, lowercase, numbers, and symbols.');
        }

        return [
            'name' => $name,
            'email' => $email,
            'password' => $pass,
        ];
    }

    /**
     * @param  array{companyName: string, countryCode: string, timezone: string, currency: string, taxRate: string, taxLabel: string, dateFormat: string, timeFormat: string, fiscalYearStart: int}  $company
     * @param  array<int, array{name: string, street: string, city: string, county: string, postcode: string, country_code: string, is_default: bool}>  $stores
     * @param  array{primaryColour: string, accentColour: string, logoPath: string|null}  $branding
     * @param  array{name: string, email: string, password: string}  $admin
     */
    private function reviewAndConfirm(
        array $company,
        array $stores,
        FeatureProfile $profile,
        array $branding,
        array $admin,
    ): bool {
        if (! $this->interactive) {
            return true;
        }

        if ($this->option('force')) {
            return true;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=white;options=bold>Review</>', '');
        $this->newLine();

        $this->components->twoColumnDetail('Company', $company['companyName']);
        $this->components->twoColumnDetail('Country', $company['countryCode']);
        $this->components->twoColumnDetail('Timezone', $company['timezone']);
        $this->components->twoColumnDetail('Currency', $company['currency']);
        $this->components->twoColumnDetail('Tax', $company['taxRate'].'% '.$company['taxLabel']);

        $this->newLine();
        $storeNames = array_map(fn ($s) => $s['name'], $stores);
        $this->components->twoColumnDetail('Stores', implode(', ', $storeNames));

        $this->newLine();
        $this->components->twoColumnDetail('Profile', $profile->label());

        $this->newLine();
        $this->components->twoColumnDetail('Primary Colour', $branding['primaryColour']);
        $this->components->twoColumnDetail('Accent Colour', $branding['accentColour']);
        $this->components->twoColumnDetail('Logo', $branding['logoPath'] ?? 'None');

        $this->newLine();
        $this->components->twoColumnDetail('Admin Name', $admin['name']);
        $this->components->twoColumnDetail('Admin Email', $admin['email']);

        $this->newLine();

        return confirm('Proceed with setup?', true);
    }

    private function processLogo(?string $localPath): ?string
    {
        if (! $localPath) {
            return null;
        }

        if (! file_exists($localPath)) {
            throw new RuntimeException("Logo file not found: {$localPath}");
        }

        $extension = pathinfo($localPath, PATHINFO_EXTENSION);
        $storagePath = 'branding/logo.'.$extension;

        Storage::disk('public')->put($storagePath, file_get_contents($localPath));

        return $storagePath;
    }

    /**
     * Get a value from a command option, or fall back to an interactive prompt.
     */
    private function optionOrPrompt(
        string $optionName,
        string $label,
        string $default = '',
        bool $required = true,
        bool $secret = false,
    ): string {
        $value = $this->option($optionName);

        if ($value !== null) {
            if ($required && $value === '') {
                throw new RuntimeException("The --{$optionName} option must not be empty.");
            }

            return $value;
        }

        if (! $this->interactive) {
            if ($required && $default === '') {
                throw new RuntimeException("The --{$optionName} option is required in non-interactive mode.");
            }

            return $default;
        }

        if ($secret) {
            return password(label: $label, required: $required);
        }

        return text(label: $label, default: $default, required: $required);
    }

    /**
     * Get a value from a command option, or fall back to an interactive select prompt.
     *
     * @param  array<string, string>  $options
     */
    private function optionOrSelect(string $optionName, string $label, array $options, string $default): string
    {
        $value = $this->option($optionName);

        if ($value !== null) {
            if (! array_key_exists($value, $options)) {
                throw new RuntimeException(
                    "Invalid value '{$value}' for --{$optionName}. Allowed: ".implode(', ', array_keys($options))
                );
            }

            return $value;
        }

        if (! $this->interactive) {
            return $default;
        }

        return select(label: $label, options: $options, default: $default);
    }
}

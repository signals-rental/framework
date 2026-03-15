<?php

namespace App\Actions\Setup;

use App\Data\Setup\CompleteSetupData;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;

class CompleteSetup
{
    public function __invoke(CompleteSetupData $data): User
    {
        $this->seedReferenceData();
        $this->writeCompanySettings($data);
        $this->writeModuleSettings($data);
        $this->writeBrandingSettings($data);
        $this->createStores($data);
        $user = $this->createAdminUser($data);
        $this->recordSetupMetadata($data);
        $this->markSetupComplete();

        return $user;
    }

    private function seedReferenceData(): void
    {
        $seeders = [
            \Database\Seeders\CountrySeeder::class,
            \Database\Seeders\ListOfValuesSeeder::class,
            \Database\Seeders\TaxClassSeeder::class,
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\EmailTemplateSeeder::class,
            \Database\Seeders\NotificationTypeSeeder::class,
        ];

        foreach ($seeders as $seederClass) {
            app($seederClass)->run();
        }
    }

    private function writeCompanySettings(CompleteSetupData $data): void
    {
        settings()->setMany([
            'company.name' => $data->companyName,
            'company.country_code' => $data->countryCode,
            'company.timezone' => $data->timezone,
            'company.currency' => $data->currency,
            'company.tax_rate' => $data->taxRate,
            'company.tax_label' => $data->taxLabel,
            'company.date_format' => $data->dateFormat,
            'company.time_format' => $data->timeFormat,
            'company.fiscal_year_start' => ['value' => $data->fiscalYearStart, 'type' => 'integer'],
        ]);
    }

    private function writeModuleSettings(CompleteSetupData $data): void
    {
        $modules = [];
        foreach ($data->profile->modules() as $module => $enabled) {
            $modules["modules.{$module}"] = ['value' => $enabled, 'type' => 'boolean'];
        }

        settings()->setMany($modules);
    }

    private function writeBrandingSettings(CompleteSetupData $data): void
    {
        settings()->setMany([
            'branding.primary_colour' => $data->primaryColour,
            'branding.accent_colour' => $data->accentColour,
            'branding.logo_path' => $data->logoPath ?? '',
        ]);
    }

    private function createStores(CompleteSetupData $data): void
    {
        if (empty($data->stores)) {
            Store::create([
                'name' => $data->companyName,
                'country_code' => $data->countryCode,
                'is_default' => true,
            ]);

            return;
        }

        foreach ($data->stores as $index => $storeData) {
            Store::create([
                'name' => $storeData['name'],
                'street' => $storeData['street'] ?? null,
                'city' => $storeData['city'] ?? null,
                'county' => $storeData['county'] ?? null,
                'postcode' => $storeData['postcode'] ?? null,
                'country_code' => $storeData['country_code'] ?? $data->countryCode,
                'is_default' => $storeData['is_default'] ?? ($index === 0),
            ]);
        }
    }

    private function createAdminUser(CompleteSetupData $data): User
    {
        $member = Member::create([
            'membership_type' => MembershipType::User,
            'name' => $data->adminName,
            'is_active' => true,
        ]);

        $defaultStore = Store::query()->where('is_default', true)->firstOrFail();

        Membership::create([
            'member_id' => $member->id,
            'store_id' => $defaultStore->id,
            'is_owner' => true,
            'is_admin' => true,
            'is_active' => true,
        ]);

        return User::create([
            'name' => $data->adminName,
            'email' => $data->adminEmail,
            'password' => $data->adminPassword,
            'email_verified_at' => now(),
            'is_owner' => true,
            'is_admin' => true,
            'member_id' => $member->id,
        ]);
    }

    private function recordSetupMetadata(CompleteSetupData $data): void
    {
        settings()->setMany([
            'setup.profile' => $data->profile->value,
            'setup.completed_at' => now()->toIso8601String(),
        ]);
    }

    private function markSetupComplete(): void
    {
        Env::writeVariables(
            ['SIGNALS_SETUP_COMPLETE' => 'true'],
            app()->basePath('.env'),
            overwrite: true,
        );

        config(['signals.setup_complete' => true]);

        if (app()->runningUnitTests()) {
            Artisan::call('config:clear');
        } else {
            Artisan::call('config:cache');
        }
    }
}

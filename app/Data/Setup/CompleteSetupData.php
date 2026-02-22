<?php

namespace App\Data\Setup;

use App\Enums\FeatureProfile;

class CompleteSetupData
{
    /**
     * @param  array<int, array{name: string, street?: string|null, city?: string|null, county?: string|null, postcode?: string|null, country_code?: string|null, is_default?: bool}>  $stores
     */
    public function __construct(
        public string $companyName,
        public string $countryCode,
        public string $timezone,
        public string $currency,
        public string $taxRate,
        public string $taxLabel,
        public string $dateFormat,
        public string $timeFormat,
        public int $fiscalYearStart = 1,
        public FeatureProfile $profile = FeatureProfile::General,
        public array $stores = [],
        public string $primaryColour = '#1e3a5f',
        public string $accentColour = '#3b82f6',
        public ?string $logoPath = null,
        public string $adminName = '',
        public string $adminEmail = '',
        public string $adminPassword = '',
    ) {}
}

<?php

namespace App\Data\Setup;

use App\Enums\FeatureProfile;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CompleteSetupData extends Data
{
    /**
     * @param  array<int, array{name: string, street?: string|null, city?: string|null, county?: string|null, postcode?: string|null, country_code?: string|null, is_default?: bool}>  $stores
     */
    public function __construct(
        #[Required, Max(255)]
        public string $companyName,
        #[Required, Max(2)]
        public string $countryCode,
        #[Required]
        public string $timezone,
        #[Required]
        public string $currency,
        #[Required]
        public string $taxRate,
        #[Required]
        public string $taxLabel,
        #[Required]
        public string $dateFormat,
        #[Required]
        public string $timeFormat,
        public int $fiscalYearStart = 1,
        public FeatureProfile $profile = FeatureProfile::General,
        public array $stores = [],
        #[Max(7)]
        public string $primaryColour = '#1e3a5f',
        #[Max(7)]
        public string $accentColour = '#3b82f6',
        public ?string $logoPath = null,
        public string $adminName = '',
        public string $adminEmail = '',
        public string $adminPassword = '',
    ) {}
}

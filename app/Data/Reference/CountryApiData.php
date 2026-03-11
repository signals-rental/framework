<?php

namespace App\Data\Reference;

use App\Models\Country;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class CountryApiData extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $code3,
        public string $name,
        public ?string $currency_code,
        public ?string $phone_prefix,
        public ?string $default_timezone,
        public ?string $default_date_format,
        public ?string $default_time_format,
        public ?string $default_number_format,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(Country $country): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $country->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $country->updated_at;

        return new self(
            id: $country->id,
            code: $country->code,
            code3: $country->code3,
            name: $country->name,
            currency_code: $country->currency_code,
            phone_prefix: $country->phone_prefix,
            default_timezone: $country->default_timezone,
            default_date_format: $country->default_date_format,
            default_time_format: $country->default_time_format,
            default_number_format: $country->default_number_format,
            is_active: $country->is_active,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}

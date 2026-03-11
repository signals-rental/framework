<?php

namespace App\Data\Members;

use Spatie\LaravelData\Data;

class UpdateAddressData extends Data
{
    public function __construct(
        public ?string $street = null,
        public ?string $name = null,
        public ?string $city = null,
        public ?string $county = null,
        public ?string $postcode = null,
        public ?int $country_id = null,
        public ?int $type_id = null,
        public ?bool $is_primary = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'street' => ['sometimes', 'string'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postcode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
            'type_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
            'is_primary' => ['sometimes', 'boolean'],
            'latitude' => ['sometimes', 'nullable', 'numeric'],
            'longitude' => ['sometimes', 'nullable', 'numeric'],
        ];
    }
}

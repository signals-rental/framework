<?php

namespace App\Data\Stores;

use Spatie\LaravelData\Data;

class UpdateStoreData extends Data
{
    /**
     * @param  list<string>|null  $tag_list
     */
    public function __construct(
        public ?string $name = null,
        public ?string $street = null,
        public ?string $city = null,
        public ?string $county = null,
        public ?string $postcode = null,
        public ?string $country_code = null,
        public ?int $country_id = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?bool $is_default = null,
        public ?array $tag_list = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postcode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'country_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'tag_list' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

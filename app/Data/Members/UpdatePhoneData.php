<?php

namespace App\Data\Members;

use Spatie\LaravelData\Data;

class UpdatePhoneData extends Data
{
    public function __construct(
        public ?string $number = null,
        public ?string $country_code = null,
        public ?int $type_id = null,
        public ?bool $is_primary = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'number' => ['sometimes', 'string', 'max:50'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'type_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}

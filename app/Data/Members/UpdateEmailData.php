<?php

namespace App\Data\Members;

use Spatie\LaravelData\Data;

class UpdateEmailData extends Data
{
    public function __construct(
        public ?string $address = null,
        public ?int $type_id = null,
        public ?bool $is_primary = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'address' => ['sometimes', 'string', 'email', 'max:255'],
            'type_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}

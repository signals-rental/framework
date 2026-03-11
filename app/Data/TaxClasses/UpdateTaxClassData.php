<?php

namespace App\Data\TaxClasses;

use Spatie\LaravelData\Data;

class UpdateTaxClassData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $is_default = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}

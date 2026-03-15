<?php

namespace App\Data\Tax;

use Spatie\LaravelData\Data;

class UpdateTaxRateData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $rate = null,
        public ?bool $is_active = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

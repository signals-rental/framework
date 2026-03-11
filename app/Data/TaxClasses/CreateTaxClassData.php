<?php

namespace App\Data\TaxClasses;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateTaxClassData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        public ?string $description = null,
        public bool $is_default = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}

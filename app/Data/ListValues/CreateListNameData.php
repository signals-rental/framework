<?php

namespace App\Data\ListValues;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateListNameData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        public ?string $description = null,
        public bool $is_system = false,
        public bool $is_hierarchical = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:list_names,name'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_system' => ['sometimes', 'boolean'],
            'is_hierarchical' => ['sometimes', 'boolean'],
        ];
    }
}

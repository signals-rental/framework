<?php

namespace App\Data\ListValues;

use Spatie\LaravelData\Data;

class UpdateListNameData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $is_hierarchical = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_hierarchical' => ['sometimes', 'boolean'],
        ];
    }
}

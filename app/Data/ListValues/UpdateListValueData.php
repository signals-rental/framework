<?php

namespace App\Data\ListValues;

use Spatie\LaravelData\Data;

class UpdateListValueData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public ?string $name = null,
        public ?int $parent_id = null,
        public ?int $sort_order = null,
        public ?bool $is_active = null,
        public ?array $metadata = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

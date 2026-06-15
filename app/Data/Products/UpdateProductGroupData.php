<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateProductGroupData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public int|null|Optional $parent_id = new Optional,
        public ?int $sort_order = null,
        /** @var array<string, mixed>|null */
        public ?array $custom_fields = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:product_groups,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}

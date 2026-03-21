<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class CreateProductGroupData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?int $parent_id = null,
        public int $sort_order = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:product_groups,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

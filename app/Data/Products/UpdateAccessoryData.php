<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class UpdateAccessoryData extends Data
{
    public function __construct(
        public ?int $accessory_product_id = null,
        public ?string $quantity = null,
        public ?bool $included = null,
        public ?bool $zero_priced = null,
        public ?int $sort_order = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'accessory_product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'included' => ['sometimes', 'boolean'],
            'zero_priced' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

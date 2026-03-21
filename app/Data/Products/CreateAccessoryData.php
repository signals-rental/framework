<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class CreateAccessoryData extends Data
{
    public function __construct(
        public int $product_id,
        public int $accessory_product_id,
        public string $quantity = '1.0',
        public bool $included = false,
        public bool $zero_priced = false,
        public int $sort_order = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'accessory_product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'included' => ['sometimes', 'boolean'],
            'zero_priced' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

<?php

namespace App\Data\Products;

use App\Enums\KitComponentBinding;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

/**
 * Input DTO for adding a component line to a product's kit composition.
 */
class CreateSerialisedComponentData extends Data
{
    public function __construct(
        public int $product_id,
        public int $component_product_id,
        public string $quantity = '1',
        public KitComponentBinding $binding = KitComponentBinding::Pool,
        public int $sort_order = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'component_product_id' => ['required', 'integer', Rule::exists('products', 'id')->withoutTrashed()],
            'quantity' => ['sometimes', 'numeric', 'min:1'],
            'binding' => ['sometimes', new Enum(KitComponentBinding::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

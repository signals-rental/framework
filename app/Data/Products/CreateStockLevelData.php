<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class CreateStockLevelData extends Data
{
    public function __construct(
        public int $product_id,
        public int $store_id,
        public ?int $member_id = null,
        public ?string $item_name = null,
        public ?string $asset_number = null,
        public ?string $serial_number = null,
        public ?string $barcode = null,
        public ?string $location = null,
        public int $stock_type = 1,
        public int $stock_category = 10,
        public int $quantity_held = 0,
        public int $quantity_allocated = 0,
        public int $quantity_unavailable = 0,
        public int $quantity_on_order = 0,
        public ?int $container_stock_level_id = null,
        public ?string $container_mode = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        /** @var array<string, mixed> */
        public array $custom_fields = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'member_id' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'item_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'asset_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'stock_type' => ['sometimes', 'integer', 'in:1,2'],
            'stock_category' => ['sometimes', 'integer', 'in:10,50'],
            'quantity_held' => ['sometimes', 'integer', 'min:0'],
            'quantity_allocated' => ['sometimes', 'integer', 'min:0'],
            'quantity_unavailable' => ['sometimes', 'integer', 'min:0'],
            'quantity_on_order' => ['sometimes', 'integer', 'min:0'],
            'container_stock_level_id' => ['sometimes', 'nullable', 'integer', 'exists:stock_levels,id'],
            'container_mode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}

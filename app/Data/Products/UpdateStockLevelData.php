<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class UpdateStockLevelData extends Data
{
    public function __construct(
        public ?int $product_id = null,
        public ?int $store_id = null,
        public ?int $member_id = null,
        public ?string $item_name = null,
        public ?string $asset_number = null,
        public ?string $serial_number = null,
        public ?string $barcode = null,
        public ?string $location = null,
        public ?int $stock_type = null,
        public ?int $stock_category = null,
        public ?int $quantity_held = null,
        public ?int $quantity_allocated = null,
        public ?int $quantity_unavailable = null,
        public ?int $quantity_on_order = null,
        public ?int $container_stock_level_id = null,
        public ?string $container_mode = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        /** @var array<string, mixed>|null */
        public ?array $custom_fields = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'store_id' => ['sometimes', 'integer', 'exists:stores,id'],
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

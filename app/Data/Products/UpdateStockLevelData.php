<?php

namespace App\Data\Products;

use App\Enums\AllowedStockType;
use App\Enums\StockCategory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
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
        public ?string $quantity_held = null,
        public ?string $quantity_allocated = null,
        public ?string $quantity_unavailable = null,
        public ?string $quantity_on_order = null,
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
            'stock_type' => ['sometimes', 'integer', Rule::in([AllowedStockType::Rental->value, AllowedStockType::Sale->value])],
            'stock_category' => ['sometimes', 'integer', new Enum(StockCategory::class)],
            'quantity_held' => ['sometimes', 'numeric', 'min:0'],
            'quantity_allocated' => ['sometimes', 'numeric', 'min:0'],
            'quantity_unavailable' => ['sometimes', 'numeric', 'min:0'],
            'quantity_on_order' => ['sometimes', 'numeric', 'min:0'],
            'container_stock_level_id' => ['sometimes', 'nullable', 'integer', 'exists:stock_levels,id'],
            'container_mode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}

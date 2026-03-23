<?php

namespace App\Data\Products;

use App\Enums\AllowedStockType;
use App\Enums\StockCategory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
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
        public int $stock_type = AllowedStockType::Rental->value,
        public int $stock_category = StockCategory::BulkStock->value,
        public string $quantity_held = '0',
        public string $quantity_allocated = '0',
        public string $quantity_unavailable = '0',
        public string $quantity_on_order = '0',
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

<?php

namespace App\Data\Products;

use App\Data\Concerns\EntityReferenceData;
use App\Data\Concerns\FormatsTimestamps;
use App\Enums\StockCategory;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;

class StockLevelData extends Data
{
    use FormatsTimestamps;

    /**
     * @param  array<string, mixed>  $custom_fields
     */
    public function __construct(
        public int $id,
        #[MapOutputName('item_id')]
        public int $product_id,
        public ?string $item_name,
        public int $store_id,
        public ?string $store_name,
        public ?int $member_id,
        public ?string $asset_number,
        public ?string $serial_number,
        public ?string $barcode,
        public ?string $location,
        public int $stock_type,
        public string $stock_type_name,
        public int $stock_category,
        public string $stock_category_name,
        public string $quantity_held,
        public string $quantity_allocated,
        public string $quantity_unavailable,
        public string $quantity_on_order,
        public ?int $container_stock_level_id,
        public ?string $container_mode,
        public ?string $starts_at,
        public ?string $ends_at,
        public array $custom_fields,
        public string $created_at,
        public string $updated_at,
        public ?EntityReferenceData $item = null,
    ) {}

    public static function fromModel(StockLevel $stockLevel): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $stockLevel->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $stockLevel->updated_at;

        $stockType = (int) ($stockLevel->stock_type ?? 1);
        $stockCategory = (int) ($stockLevel->stock_category ?? 10);

        return new self(
            id: $stockLevel->id,
            product_id: $stockLevel->product_id,
            item_name: $stockLevel->item_name ?? ($stockLevel->relationLoaded('product') && $stockLevel->product
                ? $stockLevel->product->name
                : null),
            store_id: $stockLevel->store_id,
            store_name: $stockLevel->relationLoaded('store') && $stockLevel->store
                ? $stockLevel->store->name
                : null,
            member_id: $stockLevel->member_id ?? null,
            asset_number: $stockLevel->asset_number ?? null,
            serial_number: $stockLevel->serial_number ?? null,
            barcode: $stockLevel->barcode ?? null,
            location: $stockLevel->location ?? null,
            stock_type: $stockType,
            stock_type_name: Product::stockTypeName($stockType),
            stock_category: $stockCategory,
            stock_category_name: StockCategory::tryFrom($stockCategory)?->label() ?? 'Unknown',
            quantity_held: number_format((float) ($stockLevel->quantity_held ?? 0), 1, '.', ''),
            quantity_allocated: number_format((float) ($stockLevel->quantity_allocated ?? 0), 1, '.', ''),
            quantity_unavailable: number_format((float) ($stockLevel->quantity_unavailable ?? 0), 1, '.', ''),
            quantity_on_order: number_format((float) ($stockLevel->quantity_on_order ?? 0), 1, '.', ''),
            container_stock_level_id: $stockLevel->container_stock_level_id ?? null,
            container_mode: $stockLevel->container_mode ?? null,
            starts_at: $stockLevel->starts_at !== null
                ? self::formatTimestamp(Carbon::parse($stockLevel->starts_at))
                : null,
            ends_at: $stockLevel->ends_at !== null
                ? self::formatTimestamp(Carbon::parse($stockLevel->ends_at))
                : null,
            custom_fields: $stockLevel->relationLoaded('customFieldValues') ? $stockLevel->custom_fields : [],
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
            item: $stockLevel->relationLoaded('product') && $stockLevel->product
                ? EntityReferenceData::from(['id' => $stockLevel->product->id, 'name' => $stockLevel->product->name])
                : null,
        );
    }
}

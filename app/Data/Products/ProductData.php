<?php

namespace App\Data\Products;

use App\Enums\StockMethod;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;

class ProductData extends Data
{
    /**
     * @param  list<string>  $tag_list
     * @param  array<string, mixed>  $custom_fields
     * @param  array<string, mixed>|null  $product_group
     * @param  array<string, mixed>|null  $tax_class
     * @param  array<string, mixed>|null  $purchase_tax_class
     * @param  array<string, mixed>|null  $rental_revenue_group
     * @param  array<string, mixed>|null  $sale_revenue_group
     * @param  array<string, mixed>|null  $sub_rental_cost_group
     * @param  array<string, mixed>|null  $purchase_cost_group
     * @param  array<string, mixed>|null  $icon
     * @param  list<array<string, mixed>>  $accessories
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public ?string $description,
        public int $product_group_id,
        #[MapOutputName('active')]
        public bool $is_active,
        public int $allowed_stock_type,
        public string $allowed_stock_type_name,
        public int $stock_method,
        public string $stock_method_name,
        public string $buffer_percent,
        public int $post_rent_unavailability,
        public string $replacement_charge,
        public ?string $weight,
        public ?string $barcode,
        public ?string $sku,
        public bool $accessory_only,
        public bool $system,
        public bool $discountable,
        public ?int $tax_class_id,
        public ?int $purchase_tax_class_id,
        public ?int $rental_revenue_group_id,
        public ?int $sale_revenue_group_id,
        public ?int $sub_rental_cost_group_id,
        public string $sub_rental_price,
        public ?int $purchase_cost_group_id,
        public string $purchase_price,
        public ?int $country_of_origin_id,
        public array $tag_list,
        public array $custom_fields,
        public string $created_at,
        public string $updated_at,
        public ?array $product_group = null,
        public ?array $tax_class = null,
        public ?array $purchase_tax_class = null,
        public ?array $rental_revenue_group = null,
        public ?array $sale_revenue_group = null,
        public ?array $sub_rental_cost_group = null,
        public ?array $purchase_cost_group = null,
        public ?array $icon = null,
        public array $accessories = [],
    ) {}

    /**
     * Format a Carbon timestamp in CRMS format (UTC with Z suffix and milliseconds).
     */
    private static function formatTimestamp(\DateTimeInterface $timestamp): string
    {
        return Carbon::instance($timestamp)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Derive a human-readable name for the allowed stock type.
     */
    private static function stockTypeName(int $type): string
    {
        return match ($type) {
            1 => 'Rental',
            2 => 'Sale',
            3 => 'Both',
            default => 'Unknown',
        };
    }

    public static function fromModel(Product $product): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $product->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $product->updated_at;

        $accessories = [];
        if ($product->relationLoaded('accessories')) {
            $accessories = $product->accessories->map(function ($acc) {
                $relatedProduct = $acc->relationLoaded('accessoryProduct') ? $acc->accessoryProduct : null;

                return [
                    'id' => $acc->id,
                    'related_id' => $acc->accessory_product_id,
                    'related_name' => $relatedProduct !== null ? $relatedProduct->name : '',
                    'quantity' => number_format((float) $acc->quantity, 1, '.', ''),
                ];
            })->all();
        }

        /** @var StockMethod $stockMethod */
        $stockMethod = $product->stock_method;

        /** @var \App\Enums\ProductType $productType */
        $productType = $product->product_type;

        return new self(
            id: $product->id,
            name: $product->name,
            type: $productType->label(),
            description: $product->description,
            product_group_id: $product->product_group_id ?? 0,
            is_active: $product->is_active,
            allowed_stock_type: $product->allowed_stock_type ?? 1,
            allowed_stock_type_name: self::stockTypeName($product->allowed_stock_type ?? 1),
            stock_method: $stockMethod->value,
            stock_method_name: $stockMethod->label(),
            buffer_percent: number_format((float) $product->buffer_percent, 1, '.', ''),
            post_rent_unavailability: $product->post_rent_unavailability ?? 0,
            replacement_charge: $product->formatMoneyCost('replacement_charge'),
            weight: $product->weight !== null ? number_format((float) $product->weight, 1, '.', '') : null,
            barcode: $product->barcode,
            sku: $product->sku,
            accessory_only: $product->accessory_only ?? false,
            system: $product->system ?? false,
            discountable: $product->discountable ?? true,
            tax_class_id: $product->tax_class_id,
            purchase_tax_class_id: $product->purchase_tax_class_id,
            rental_revenue_group_id: $product->rental_revenue_group_id,
            sale_revenue_group_id: $product->sale_revenue_group_id,
            sub_rental_cost_group_id: $product->sub_rental_cost_group_id,
            sub_rental_price: $product->formatMoneyCost('sub_rental_price'),
            purchase_cost_group_id: $product->purchase_cost_group_id,
            purchase_price: $product->formatMoneyCost('purchase_price'),
            country_of_origin_id: $product->country_of_origin_id,
            tag_list: $product->tag_list ?? [],
            custom_fields: $product->relationLoaded('customFieldValues') ? $product->custom_fields : [],
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
            product_group: $product->relationLoaded('productGroup') && $product->productGroup
                ? ['id' => $product->productGroup->id, 'name' => $product->productGroup->name]
                : null,
            tax_class: $product->relationLoaded('taxClass') && $product->taxClass
                ? ['id' => $product->taxClass->id, 'name' => $product->taxClass->name]
                : null,
            purchase_tax_class: $product->relationLoaded('purchaseTaxClass') && $product->purchaseTaxClass
                ? ['id' => $product->purchaseTaxClass->id, 'name' => $product->purchaseTaxClass->name]
                : null,
            rental_revenue_group: $product->relationLoaded('rentalRevenueGroup') && $product->rentalRevenueGroup
                ? ['id' => $product->rentalRevenueGroup->id, 'name' => $product->rentalRevenueGroup->name]
                : null,
            sale_revenue_group: $product->relationLoaded('saleRevenueGroup') && $product->saleRevenueGroup
                ? ['id' => $product->saleRevenueGroup->id, 'name' => $product->saleRevenueGroup->name]
                : null,
            sub_rental_cost_group: $product->relationLoaded('subRentalCostGroup') && $product->subRentalCostGroup
                ? ['id' => $product->subRentalCostGroup->id, 'name' => $product->subRentalCostGroup->name]
                : null,
            purchase_cost_group: $product->relationLoaded('purchaseCostGroup') && $product->purchaseCostGroup
                ? ['id' => $product->purchaseCostGroup->id, 'name' => $product->purchaseCostGroup->name]
                : null,
            icon: $product->icon_url ? [
                'url' => $product->icon_url,
                'thumb_url' => $product->icon_thumb_url,
            ] : null,
            accessories: $accessories,
        );
    }
}

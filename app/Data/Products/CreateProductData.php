<?php

namespace App\Data\Products;

use App\Enums\ProductType;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

class CreateProductData extends Data
{
    public function __construct(
        public string $name,
        public ProductType $product_type = ProductType::Rental,
        public ?string $description = null,
        public ?int $product_group_id = null,
        public int $allowed_stock_type = 1,
        public int $stock_method = 1,
        public ?string $weight = null,
        public ?string $barcode = null,
        public ?string $sku = null,
        public int $replacement_charge = 0,
        public string $buffer_percent = '0.0',
        public int $post_rent_unavailability = 0,
        public bool $is_active = true,
        public bool $accessory_only = false,
        public bool $system = false,
        public bool $discountable = true,
        public ?int $tax_class_id = null,
        public ?int $purchase_tax_class_id = null,
        public ?int $rental_revenue_group_id = null,
        public ?int $sale_revenue_group_id = null,
        public ?int $sub_rental_cost_group_id = null,
        public int $sub_rental_price = 0,
        public ?int $purchase_cost_group_id = null,
        public int $purchase_price = 0,
        public ?int $country_of_origin_id = null,
        /** @var list<string>|null */
        public ?array $tag_list = null,
        /** @var array<string, mixed> */
        public array $custom_fields = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'product_type' => ['sometimes', new Enum(ProductType::class)],
            'description' => ['sometimes', 'nullable', 'string'],
            'product_group_id' => ['sometimes', 'nullable', 'integer', 'exists:product_groups,id'],
            'allowed_stock_type' => ['sometimes', 'integer', 'in:1,2,3'],
            'stock_method' => ['sometimes', 'integer', 'in:1,2'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255'],
            'replacement_charge' => ['sometimes', 'integer', 'min:0'],
            'buffer_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'post_rent_unavailability' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'accessory_only' => ['sometimes', 'boolean'],
            'system' => ['sometimes', 'boolean'],
            'discountable' => ['sometimes', 'boolean'],
            'tax_class_id' => ['sometimes', 'nullable', 'integer', 'exists:product_tax_classes,id'],
            'purchase_tax_class_id' => ['sometimes', 'nullable', 'integer', 'exists:product_tax_classes,id'],
            'rental_revenue_group_id' => ['sometimes', 'nullable', 'integer', 'exists:revenue_groups,id'],
            'sale_revenue_group_id' => ['sometimes', 'nullable', 'integer', 'exists:revenue_groups,id'],
            'sub_rental_cost_group_id' => ['sometimes', 'nullable', 'integer', 'exists:cost_groups,id'],
            'sub_rental_price' => ['sometimes', 'integer', 'min:0'],
            'purchase_cost_group_id' => ['sometimes', 'nullable', 'integer', 'exists:cost_groups,id'],
            'purchase_price' => ['sometimes', 'integer', 'min:0'],
            'country_of_origin_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
            'tag_list' => ['sometimes', 'nullable', 'array'],
            'tag_list.*' => ['string'],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}

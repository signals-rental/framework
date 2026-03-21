<?php

namespace App\Actions\Products;

use App\Data\Products\CreateProductData;
use App\Data\Products\ProductData;
use App\Events\AuditableEvent;
use App\Models\Product;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateProduct
{
    public function __invoke(CreateProductData $data): ProductData
    {
        Gate::authorize('products.create');

        app(CustomFieldValidator::class)->validate('Product', $data->custom_fields, enforceRequired: true);

        return DB::transaction(function () use ($data): ProductData {
            $product = Product::create([
                'name' => $data->name,
                'product_type' => $data->product_type,
                'stock_method' => $data->stock_method,
                'is_active' => $data->is_active,
                'description' => $data->description,
                'product_group_id' => $data->product_group_id,
                'allowed_stock_type' => $data->allowed_stock_type,
                'weight' => $data->weight,
                'barcode' => $data->barcode,
                'sku' => $data->sku,
                'replacement_charge' => $data->replacement_charge,
                'buffer_percent' => $data->buffer_percent,
                'post_rent_unavailability' => $data->post_rent_unavailability,
                'accessory_only' => $data->accessory_only,
                'system' => $data->system,
                'discountable' => $data->discountable,
                'tax_class_id' => $data->tax_class_id,
                'purchase_tax_class_id' => $data->purchase_tax_class_id,
                'rental_revenue_group_id' => $data->rental_revenue_group_id,
                'sale_revenue_group_id' => $data->sale_revenue_group_id,
                'sub_rental_cost_group_id' => $data->sub_rental_cost_group_id,
                'sub_rental_price' => $data->sub_rental_price,
                'purchase_cost_group_id' => $data->purchase_cost_group_id,
                'purchase_price' => $data->purchase_price,
                'country_of_origin_id' => $data->country_of_origin_id,
                'tag_list' => $data->tag_list,
            ]);

            $product->syncCustomFields($data->custom_fields, applyDefaults: true);

            event(new AuditableEvent($product, 'product.created'));

            app(\App\Services\Api\WebhookService::class)->dispatch('product.created', [
                'product' => ProductData::fromModel($product)->toArray(),
            ]);

            return ProductData::fromModel($product);
        });
    }
}

<?php

namespace App\Actions\Products;

use App\Data\Products\ProductData;
use App\Data\Products\UpdateProductData;
use App\Events\AuditableEvent;
use App\Models\Product;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateProduct
{
    public function __invoke(Product $product, UpdateProductData $data): ProductData
    {
        Gate::authorize('products.edit');

        return DB::transaction(function () use ($product, $data): ProductData {
            $product->update(array_filter($data->toArray(), fn ($v) => $v !== null));

            if ($data->custom_fields !== null) {
                app(CustomFieldValidator::class)->validate('Product', $data->custom_fields);
                $product->syncCustomFields($data->custom_fields);
            }

            $product->refresh();

            event(new AuditableEvent($product, 'product.updated'));

            app(\App\Services\Api\WebhookService::class)->dispatch('product.updated', [
                'product' => ProductData::fromModel($product)->toArray(),
            ]);

            return ProductData::fromModel($product);
        });
    }
}

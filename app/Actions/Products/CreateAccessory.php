<?php

namespace App\Actions\Products;

use App\Data\Products\AccessoryData;
use App\Data\Products\CreateAccessoryData;
use App\Events\AuditableEvent;
use App\Models\Accessory;
use App\Models\Product;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class CreateAccessory
{
    public function __invoke(CreateAccessoryData $data): AccessoryData
    {
        Gate::authorize('products.edit');

        $product = Product::findOrFail($data->product_id);

        $accessory = $product->accessories()->create([
            'accessory_product_id' => $data->accessory_product_id,
            'quantity' => $data->quantity,
            'included' => $data->included,
            'zero_priced' => $data->zero_priced,
            'sort_order' => $data->sort_order,
        ]);

        /** @var Accessory $accessory */
        $accessory->load('accessoryProduct');

        event(new AuditableEvent($accessory, 'accessory.created'));

        app(WebhookService::class)->dispatch('product.updated', [
            'product_id' => $product->id,
        ]);

        return AccessoryData::fromModel($accessory);
    }
}

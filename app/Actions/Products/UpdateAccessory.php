<?php

namespace App\Actions\Products;

use App\Data\Products\AccessoryData;
use App\Data\Products\UpdateAccessoryData;
use App\Events\AuditableEvent;
use App\Models\Accessory;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class UpdateAccessory
{
    public function __invoke(Accessory $accessory, UpdateAccessoryData $data): AccessoryData
    {
        Gate::authorize('products.edit');

        $accessory->update(
            collect($data->toArray())
                ->reject(fn ($value) => $value === null)
                ->all()
        );

        $accessory->refresh();
        $accessory->load('accessoryProduct');

        event(new AuditableEvent($accessory, 'accessory.updated'));

        app(WebhookService::class)->dispatch('product.updated', [
            'product_id' => $accessory->product_id,
        ]);

        return AccessoryData::fromModel($accessory);
    }
}

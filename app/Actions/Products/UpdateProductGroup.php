<?php

namespace App\Actions\Products;

use App\Data\Products\ProductGroupData;
use App\Data\Products\UpdateProductGroupData;
use App\Events\AuditableEvent;
use App\Models\ProductGroup;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class UpdateProductGroup
{
    public function __invoke(ProductGroup $group, UpdateProductGroupData $data): ProductGroupData
    {
        Gate::authorize('products.edit');

        $group->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        $group->refresh();

        event(new AuditableEvent($group, 'product_group.updated'));

        app(WebhookService::class)->dispatch('product_group.updated', [
            'product_group' => ProductGroupData::fromModel($group)->toArray(),
        ]);

        return ProductGroupData::fromModel($group);
    }
}

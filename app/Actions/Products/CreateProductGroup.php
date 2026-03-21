<?php

namespace App\Actions\Products;

use App\Data\Products\CreateProductGroupData;
use App\Data\Products\ProductGroupData;
use App\Events\AuditableEvent;
use App\Models\ProductGroup;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class CreateProductGroup
{
    public function __invoke(CreateProductGroupData $data): ProductGroupData
    {
        Gate::authorize('products.create');

        $group = ProductGroup::create([
            'name' => $data->name,
            'description' => $data->description,
            'parent_id' => $data->parent_id,
            'sort_order' => $data->sort_order,
        ]);

        event(new AuditableEvent($group, 'product_group.created'));

        app(WebhookService::class)->dispatch('product_group.created', [
            'product_group' => ProductGroupData::fromModel($group)->toArray(),
        ]);

        return ProductGroupData::fromModel($group);
    }
}

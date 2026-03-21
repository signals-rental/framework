<?php

namespace App\Actions\Products;

use App\Events\AuditableEvent;
use App\Models\ProductGroup;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class DeleteProductGroup
{
    public function __invoke(ProductGroup $productGroup): void
    {
        Gate::authorize('products.delete');

        event(new AuditableEvent($productGroup, 'product_group.deleted'));

        app(WebhookService::class)->dispatch('product_group.deleted', [
            'id' => $productGroup->id,
        ]);

        $productGroup->delete();
    }
}

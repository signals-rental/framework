<?php

namespace App\Actions\Products;

use App\Events\AuditableEvent;
use App\Models\Product;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class DeleteProduct
{
    public function __invoke(Product $product): void
    {
        Gate::authorize('products.delete');

        event(new AuditableEvent($product, 'product.deleted'));

        app(WebhookService::class)->dispatch('product.deleted', [
            'id' => $product->id,
        ]);

        $product->delete();
    }
}

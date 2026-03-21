<?php

namespace App\Actions\Products;

use App\Events\AuditableEvent;
use App\Models\Product;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteProduct
{
    public function __invoke(Product $product): void
    {
        Gate::authorize('products.delete');

        DB::transaction(function () use ($product): void {
            event(new AuditableEvent($product, 'product.deleted'));

            // Delete webhooks send only the ID as the resource no longer exists.
            app(WebhookService::class)->dispatch('product.deleted', [
                'id' => $product->id,
            ]);

            $product->delete();
        });
    }
}

<?php

namespace App\Actions\Products;

use App\Events\AuditableEvent;
use App\Models\Product;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RestoreProduct
{
    public function __invoke(Product $product): void
    {
        Gate::authorize('products.delete');

        if (! $product->trashed()) {
            return;
        }

        DB::transaction(function () use ($product): void {
            $product->restore();

            event(new AuditableEvent($product, 'product.restored'));
        });

        // Dispatch the webhook only after the transaction has committed. All queue
        // connections use after_commit: false, so dispatching inside the closure
        // would queue a delivery even if the transaction later rolled back.
        app(WebhookService::class)->dispatch('product.restored', [
            'id' => $product->id,
        ]);
    }
}

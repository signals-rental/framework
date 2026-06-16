<?php

namespace App\Actions\Products;

use App\Events\AuditableEvent;
use App\Models\Product;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveProduct
{
    public function __invoke(Product $product): void
    {
        Gate::authorize('products.delete');

        if ($product->trashed()) {
            return;
        }

        DB::transaction(function () use ($product): void {
            event(new AuditableEvent($product, 'product.archived'));

            $product->delete();
        });

        // DeliverWebhook sets afterCommit = true, so each delivery only enqueues
        // after the surrounding transaction commits (and is dropped on rollback).
        // Dispatch placement relative to the transaction is therefore safe either way.
        app(WebhookService::class)->dispatch('product.archived', [
            'id' => $product->id,
        ]);
    }
}

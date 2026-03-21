<?php

namespace App\Actions\Products;

use App\Events\AuditableEvent;
use App\Models\StockLevel;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteStockLevel
{
    public function __invoke(StockLevel $stockLevel): void
    {
        Gate::authorize('stock.adjust');

        DB::transaction(function () use ($stockLevel): void {
            event(new AuditableEvent($stockLevel, 'stock_level.deleted'));

            // Delete webhooks send only the ID as the resource no longer exists.
            app(WebhookService::class)->dispatch('stock_level.deleted', [
                'id' => $stockLevel->id,
            ]);

            $stockLevel->delete();
        });
    }
}

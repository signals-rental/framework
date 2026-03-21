<?php

namespace App\Actions\Products;

use App\Events\AuditableEvent;
use App\Models\StockLevel;
use Illuminate\Support\Facades\Gate;

class DeleteStockLevel
{
    public function __invoke(StockLevel $stockLevel): void
    {
        Gate::authorize('stock.adjust');

        event(new AuditableEvent($stockLevel, 'stock_level.deleted'));

        app(\App\Services\Api\WebhookService::class)->dispatch('stock_level.deleted', [
            'id' => $stockLevel->id,
        ]);

        $stockLevel->delete();
    }
}

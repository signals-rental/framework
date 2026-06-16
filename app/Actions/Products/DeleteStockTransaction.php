<?php

namespace App\Actions\Products;

use App\Data\Products\StockTransactionData;
use App\Events\AuditableEvent;
use App\Models\StockTransaction;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteStockTransaction
{
    public function __invoke(StockTransaction $transaction): void
    {
        Gate::authorize('stock.adjust');

        DB::transaction(function () use ($transaction): void {
            $stockLevel = $transaction->stockLevel()->firstOrFail();

            // Reverse this transaction's effect on held stock by decrementing
            // the same signed quantity that creation incremented.
            $stockLevel->decrement('quantity_held', (float) $transaction->signedQuantity());

            $payload = StockTransactionData::fromModel($transaction)->toArray();

            event(new AuditableEvent($transaction, 'stock_transaction.deleted'));

            $transaction->delete();

            // Safe to dispatch inside the transaction: DeliverWebhook sets
            // afterCommit = true, so the delivery only enqueues after commit
            // (and is dropped if this transaction rolls back).
            app(WebhookService::class)->dispatch('stock_transaction.deleted', [
                'stock_transaction' => $payload,
            ]);
        });
    }
}

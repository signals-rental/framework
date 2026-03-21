<?php

namespace App\Actions\Products;

use App\Data\Products\CreateStockTransactionData;
use App\Data\Products\StockTransactionData;
use App\Enums\TransactionType;
use App\Events\AuditableEvent;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\Gate;

class CreateStockTransaction
{
    public function __invoke(CreateStockTransactionData $data): StockTransactionData
    {
        Gate::authorize('stock.adjust');

        $stockLevel = StockLevel::findOrFail($data->stock_level_id);

        $transaction = StockTransaction::create([
            'stock_level_id' => $data->stock_level_id,
            'store_id' => $data->store_id ?? $stockLevel->store_id,
            'transaction_type' => $data->transaction_type,
            'transaction_at' => $data->transaction_at ?? now(),
            'quantity' => $data->quantity,
            'description' => $data->description,
            'manual' => true,
        ]);

        // Update stock level quantity based on transaction type
        /** @var TransactionType $type */
        $type = $transaction->transaction_type;
        $signedQty = (float) $data->quantity * $type->quantitySign();
        $stockLevel->increment('quantity_held', $signedQty);

        event(new AuditableEvent($transaction, 'stock_transaction.created'));

        return StockTransactionData::fromModel($transaction);
    }
}

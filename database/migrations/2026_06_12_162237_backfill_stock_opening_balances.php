<?php

use App\Enums\TransactionType;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Reconcile each stock level so its held quantity equals the sum of its
     * transactions. Any difference (typically the initial quantity set at
     * creation, before Opening transactions were recorded) is captured as a
     * reconciling Opening/adjustment transaction.
     */
    public function up(): void
    {
        StockLevel::query()->with('stockTransactions')->chunkById(200, function ($stockLevels): void {
            foreach ($stockLevels as $stockLevel) {
                $sum = 0.0;
                foreach ($stockLevel->stockTransactions as $transaction) {
                    /** @var TransactionType $type */
                    $type = $transaction->transaction_type;
                    $sum += (float) bcmul((string) $transaction->quantity, (string) $type->quantitySign(), 2);
                }

                $difference = round((float) $stockLevel->quantity_held - $sum, 2);

                if (abs($difference) < 0.01) {
                    continue;
                }

                StockTransaction::create([
                    'stock_level_id' => $stockLevel->id,
                    'store_id' => $stockLevel->store_id,
                    'transaction_type' => $difference > 0
                        ? TransactionType::Opening->value
                        : TransactionType::Decrease->value,
                    'transaction_at' => $stockLevel->created_at ?? now(),
                    'quantity' => abs($difference),
                    'description' => 'Opening balance (reconciled)',
                    'manual' => false,
                ]);
            }
        });
    }

    public function down(): void
    {
        StockTransaction::query()
            ->where('description', 'Opening balance (reconciled)')
            ->delete();
    }
};

<?php

use App\Enums\StockCategory;
use App\Enums\StockMethod;
use App\Models\StockLevel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Realign each stock level's stock_category with its parent product's
     * stock_method. Historically stock_category defaulted to BulkStock on
     * creation regardless of the product, so stock for serialised products was
     * stored as Bulk — making the "Serialised Stock" system view return nothing.
     */
    public function up(): void
    {
        StockLevel::query()->with('product:id,stock_method')->chunkById(500, function ($stockLevels): void {
            foreach ($stockLevels as $stockLevel) {
                $expected = $stockLevel->product?->stock_method === StockMethod::Serialised
                    ? StockCategory::SerialisedStock->value
                    : StockCategory::BulkStock->value;

                $current = (int) $stockLevel->getRawOriginal('stock_category');

                if ($current === $expected) {
                    continue;
                }

                $stockLevel->stock_category = $expected;
                $stockLevel->save();
            }
        });
    }

    public function down(): void
    {
        // No-op: the prior stock_category values were incorrect and are not
        // worth restoring.
    }
};

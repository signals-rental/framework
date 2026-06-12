<?php

namespace App\Actions\Products;

use App\Data\Products\CreateStockLevelData;
use App\Data\Products\StockLevelData;
use App\Enums\StockMethod;
use App\Enums\TransactionType;
use App\Events\AuditableEvent;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Services\Api\WebhookService;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateStockLevel
{
    public function __invoke(CreateStockLevelData $data): StockLevelData
    {
        Gate::authorize('stock.adjust');

        // Bulk products may only ever have a single stock level.
        $product = Product::find($data->product_id);
        if ($product && $product->stock_method !== StockMethod::Serialised
            && StockLevel::where('product_id', $data->product_id)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'Bulk products can only have a single stock level.',
            ]);
        }

        // Asset/barcode and serial numbers are globally unique.
        StockLevel::assertUniqueIdentifiers($data->asset_number, $data->serial_number);

        app(CustomFieldValidator::class)->validate('StockLevel', $data->custom_fields, enforceRequired: true);

        return DB::transaction(function () use ($data): StockLevelData {
            $stockLevel = StockLevel::create([
                'product_id' => $data->product_id,
                'store_id' => $data->store_id,
                'member_id' => $data->member_id,
                'item_name' => $data->item_name,
                'asset_number' => $data->asset_number,
                'serial_number' => $data->serial_number,
                'barcode' => $data->barcode,
                'location' => $data->location,
                'stock_type' => $data->stock_type,
                'stock_category' => $data->stock_category,
                'quantity_held' => $data->quantity_held,
                'quantity_allocated' => $data->quantity_allocated,
                'quantity_unavailable' => $data->quantity_unavailable,
                'quantity_on_order' => $data->quantity_on_order,
                'container_stock_level_id' => $data->container_stock_level_id,
                'container_mode' => $data->container_mode,
                'starts_at' => $data->starts_at,
                'ends_at' => $data->ends_at,
            ]);

            $stockLevel->syncCustomFields($data->custom_fields, applyDefaults: true);

            // Record the initial quantity as an Opening transaction so the held
            // quantity always equals the sum of its (non-deleted) transactions.
            if ((float) $stockLevel->quantity_held !== 0.0) {
                StockTransaction::create([
                    'stock_level_id' => $stockLevel->id,
                    'store_id' => $stockLevel->store_id,
                    'transaction_type' => TransactionType::Opening->value,
                    'transaction_at' => now(),
                    'quantity' => $stockLevel->quantity_held,
                    'description' => 'Opening balance',
                    'manual' => false,
                ]);
            }

            event(new AuditableEvent($stockLevel, 'stock_level.created'));

            app(WebhookService::class)->dispatch('stock_level.created', [
                'stock_level' => StockLevelData::fromModel($stockLevel)->toArray(),
            ]);

            return StockLevelData::fromModel($stockLevel);
        });
    }
}

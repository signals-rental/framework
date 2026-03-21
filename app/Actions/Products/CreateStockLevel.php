<?php

namespace App\Actions\Products;

use App\Data\Products\CreateStockLevelData;
use App\Data\Products\StockLevelData;
use App\Events\AuditableEvent;
use App\Models\StockLevel;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateStockLevel
{
    public function __invoke(CreateStockLevelData $data): StockLevelData
    {
        Gate::authorize('stock.adjust');

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

            event(new AuditableEvent($stockLevel, 'stock_level.created'));

            app(\App\Services\Api\WebhookService::class)->dispatch('stock_level.created', [
                'stock_level' => StockLevelData::fromModel($stockLevel)->toArray(),
            ]);

            return StockLevelData::fromModel($stockLevel);
        });
    }
}

<?php

namespace App\Actions\Products;

use App\Data\Products\StockLevelData;
use App\Data\Products\UpdateStockLevelData;
use App\Events\AuditableEvent;
use App\Models\StockLevel;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateStockLevel
{
    public function __invoke(StockLevel $stockLevel, UpdateStockLevelData $data): StockLevelData
    {
        Gate::authorize('stock.adjust');

        return DB::transaction(function () use ($stockLevel, $data): StockLevelData {
            $stockLevel->update(array_filter($data->toArray(), fn ($v) => $v !== null));

            if ($data->custom_fields !== null) {
                app(CustomFieldValidator::class)->validate('StockLevel', $data->custom_fields);
                $stockLevel->syncCustomFields($data->custom_fields);
            }

            $stockLevel->refresh();

            event(new AuditableEvent($stockLevel, 'stock_level.updated'));

            app(\App\Services\Api\WebhookService::class)->dispatch('stock_level.updated', [
                'stock_level' => StockLevelData::fromModel($stockLevel)->toArray(),
            ]);

            return StockLevelData::fromModel($stockLevel);
        });
    }
}

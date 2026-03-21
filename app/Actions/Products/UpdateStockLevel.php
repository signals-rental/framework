<?php

namespace App\Actions\Products;

use App\Data\Products\StockLevelData;
use App\Data\Products\UpdateStockLevelData;
use App\Events\AuditableEvent;
use App\Models\StockLevel;
use App\Services\Api\WebhookService;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateStockLevel
{
    /**
     * Update an existing stock level.
     *
     * Field update convention (Spatie Data Optional behaviour):
     *   - Absent key / `null` in DTO → field is not updated (retains current value)
     *   - Empty string `""` → field is cleared (set to null in database)
     */
    public function __invoke(StockLevel $stockLevel, UpdateStockLevelData $data): StockLevelData
    {
        Gate::authorize('stock.adjust');

        return DB::transaction(function () use ($stockLevel, $data): StockLevelData {
            $stockLevel->update(
                collect($data->toArray())
                    ->except(['custom_fields'])
                    ->reject(fn ($value) => $value === null)
                    ->map(fn ($value) => $value === '' ? null : $value)
                    ->all()
            );

            if ($data->custom_fields !== null) {
                app(CustomFieldValidator::class)->validate('StockLevel', $data->custom_fields);
                $stockLevel->syncCustomFields($data->custom_fields);
            }

            $stockLevel->refresh();

            event(new AuditableEvent($stockLevel, 'stock_level.updated'));

            app(WebhookService::class)->dispatch('stock_level.updated', [
                'stock_level' => StockLevelData::fromModel($stockLevel)->toArray(),
            ]);

            return StockLevelData::fromModel($stockLevel);
        });
    }
}

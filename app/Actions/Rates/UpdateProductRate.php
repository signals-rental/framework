<?php

namespace App\Actions\Rates;

use App\Data\Rates\ProductRateData;
use App\Data\Rates\UpdateProductRateData;
use App\Events\AuditableEvent;
use App\Models\ProductRate;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateProductRate
{
    public function __invoke(ProductRate $rate, UpdateProductRateData $data): ProductRateData
    {
        Gate::authorize('rates.edit');

        return DB::transaction(function () use ($rate, $data): ProductRateData {
            // toArray() omits Optional (untouched) fields but keeps explicit nulls,
            // so a client can clear a nullable field by sending it as null.
            $rate->update($data->toArray());

            $rate->refresh();

            event(new AuditableEvent($rate, 'product_rate.updated'));

            app(WebhookService::class)->dispatch('product_rate.updated', [
                'product_rate' => ProductRateData::fromModel($rate)->toArray(),
            ]);

            return ProductRateData::fromModel($rate);
        });
    }
}

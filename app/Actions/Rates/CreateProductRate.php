<?php

namespace App\Actions\Rates;

use App\Data\Rates\CreateProductRateData;
use App\Data\Rates\ProductRateData;
use App\Events\AuditableEvent;
use App\Models\ProductRate;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateProductRate
{
    public function __invoke(CreateProductRateData $data): ProductRateData
    {
        Gate::authorize('rates.create');

        return DB::transaction(function () use ($data): ProductRateData {
            $rate = ProductRate::create([
                'product_id' => $data->product_id,
                'rate_definition_id' => $data->rate_definition_id,
                'store_id' => $data->store_id,
                'transaction_type' => $data->transaction_type,
                'price' => $data->price,
                'currency' => $data->currency,
                'valid_from' => $data->valid_from,
                'valid_to' => $data->valid_to,
                'priority' => $data->priority,
            ]);

            event(new AuditableEvent($rate, 'product_rate.created'));

            app(WebhookService::class)->dispatch('product_rate.created', [
                'product_rate' => ProductRateData::fromModel($rate)->toArray(),
            ]);

            return ProductRateData::fromModel($rate);
        });
    }
}

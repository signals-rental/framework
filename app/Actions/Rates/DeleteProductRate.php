<?php

namespace App\Actions\Rates;

use App\Events\AuditableEvent;
use App\Models\ProductRate;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteProductRate
{
    public function __invoke(ProductRate $rate): void
    {
        Gate::authorize('rates.delete');

        DB::transaction(function () use ($rate): void {
            event(new AuditableEvent($rate, 'product_rate.deleted'));

            app(WebhookService::class)->dispatch('product_rate.deleted', [
                'id' => $rate->id,
            ]);

            $rate->delete();
        });
    }
}

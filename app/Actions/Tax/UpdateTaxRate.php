<?php

namespace App\Actions\Tax;

use App\Data\Tax\TaxRateData;
use App\Data\Tax\UpdateTaxRateData;
use App\Events\AuditableEvent;
use App\Models\TaxRate;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class UpdateTaxRate
{
    public function __invoke(TaxRate $taxRate, UpdateTaxRateData $data): TaxRateData
    {
        Gate::authorize('tax-classes.manage');

        $taxRate->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($taxRate, 'tax_rate.updated'));

        $data = TaxRateData::fromModel($taxRate->fresh());

        app(WebhookService::class)->dispatch('tax_rate.updated', [
            'tax_rate' => $data->toArray(),
        ]);

        return $data;
    }
}

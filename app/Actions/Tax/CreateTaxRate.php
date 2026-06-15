<?php

namespace App\Actions\Tax;

use App\Data\Tax\CreateTaxRateData;
use App\Data\Tax\TaxRateData;
use App\Events\AuditableEvent;
use App\Models\TaxRate;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class CreateTaxRate
{
    public function __invoke(CreateTaxRateData $data): TaxRateData
    {
        Gate::authorize('tax-classes.manage');

        $taxRate = TaxRate::create($data->toArray());

        event(new AuditableEvent($taxRate, 'tax_rate.created'));

        app(WebhookService::class)->dispatch('tax_rate.created', [
            'tax_rate' => TaxRateData::fromModel($taxRate)->toArray(),
        ]);

        return TaxRateData::fromModel($taxRate);
    }
}

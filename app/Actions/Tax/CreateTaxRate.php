<?php

namespace App\Actions\Tax;

use App\Data\Tax\CreateTaxRateData;
use App\Data\Tax\TaxRateData;
use App\Events\AuditableEvent;
use App\Models\TaxRate;
use Illuminate\Support\Facades\Gate;

class CreateTaxRate
{
    public function __invoke(CreateTaxRateData $data): TaxRateData
    {
        Gate::authorize('tax-classes.manage');

        $taxRate = TaxRate::create($data->toArray());

        event(new AuditableEvent($taxRate, 'tax_rate.created'));

        return TaxRateData::fromModel($taxRate);
    }
}

<?php

namespace App\Actions\Tax;

use App\Data\Tax\TaxRateData;
use App\Data\Tax\UpdateTaxRateData;
use App\Events\AuditableEvent;
use App\Models\TaxRate;
use Illuminate\Support\Facades\Gate;

class UpdateTaxRate
{
    public function __invoke(TaxRate $taxRate, UpdateTaxRateData $data): TaxRateData
    {
        Gate::authorize('tax-classes.manage');

        $taxRate->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($taxRate, 'tax_rate.updated'));

        return TaxRateData::fromModel($taxRate->fresh());
    }
}

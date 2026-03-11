<?php

namespace App\Actions\TaxClasses;

use App\Data\TaxClasses\TaxClassData;
use App\Data\TaxClasses\UpdateTaxClassData;
use App\Events\AuditableEvent;
use App\Models\ProductTaxClass;
use Illuminate\Support\Facades\Gate;

class UpdateProductTaxClass
{
    public function __invoke(ProductTaxClass $taxClass, UpdateTaxClassData $data): TaxClassData
    {
        Gate::authorize('tax-classes.manage');

        $taxClass->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($taxClass, 'product_tax_class.updated'));

        return TaxClassData::fromModel($taxClass->fresh());
    }
}

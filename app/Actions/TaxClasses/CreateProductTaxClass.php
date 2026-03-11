<?php

namespace App\Actions\TaxClasses;

use App\Data\TaxClasses\CreateTaxClassData;
use App\Data\TaxClasses\TaxClassData;
use App\Events\AuditableEvent;
use App\Models\ProductTaxClass;
use Illuminate\Support\Facades\Gate;

class CreateProductTaxClass
{
    public function __invoke(CreateTaxClassData $data): TaxClassData
    {
        Gate::authorize('tax-classes.manage');

        $taxClass = ProductTaxClass::create($data->toArray());

        event(new AuditableEvent($taxClass, 'product_tax_class.created'));

        return TaxClassData::fromModel($taxClass);
    }
}

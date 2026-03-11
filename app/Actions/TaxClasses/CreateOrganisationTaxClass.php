<?php

namespace App\Actions\TaxClasses;

use App\Data\TaxClasses\CreateTaxClassData;
use App\Data\TaxClasses\TaxClassData;
use App\Events\AuditableEvent;
use App\Models\OrganisationTaxClass;
use Illuminate\Support\Facades\Gate;

class CreateOrganisationTaxClass
{
    public function __invoke(CreateTaxClassData $data): TaxClassData
    {
        Gate::authorize('tax-classes.manage');

        $taxClass = OrganisationTaxClass::create($data->toArray());

        event(new AuditableEvent($taxClass, 'organisation_tax_class.created'));

        return TaxClassData::fromModel($taxClass);
    }
}

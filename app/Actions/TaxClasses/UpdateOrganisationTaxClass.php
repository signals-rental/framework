<?php

namespace App\Actions\TaxClasses;

use App\Data\TaxClasses\TaxClassData;
use App\Data\TaxClasses\UpdateTaxClassData;
use App\Events\AuditableEvent;
use App\Models\OrganisationTaxClass;
use Illuminate\Support\Facades\Gate;

class UpdateOrganisationTaxClass
{
    public function __invoke(OrganisationTaxClass $taxClass, UpdateTaxClassData $data): TaxClassData
    {
        Gate::authorize('tax-classes.manage');

        $taxClass->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($taxClass, 'organisation_tax_class.updated'));

        return TaxClassData::fromModel($taxClass->fresh());
    }
}

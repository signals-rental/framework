<?php

namespace App\Actions\TaxClasses;

use App\Events\AuditableEvent;
use App\Models\OrganisationTaxClass;
use Illuminate\Support\Facades\Gate;

class DeleteOrganisationTaxClass
{
    public function __invoke(OrganisationTaxClass $taxClass): void
    {
        Gate::authorize('tax-classes.manage');

        event(new AuditableEvent($taxClass, 'organisation_tax_class.deleted'));

        $taxClass->delete();
    }
}

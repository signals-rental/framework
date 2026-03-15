<?php

namespace App\Actions\Tax;

use App\Events\AuditableEvent;
use App\Models\TaxRule;
use Illuminate\Support\Facades\Gate;

class DeleteTaxRule
{
    public function __invoke(TaxRule $taxRule): void
    {
        Gate::authorize('tax-classes.manage');

        event(new AuditableEvent($taxRule, 'tax_rule.deleted'));

        $taxRule->delete();
    }
}

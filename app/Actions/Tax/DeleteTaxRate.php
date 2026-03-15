<?php

namespace App\Actions\Tax;

use App\Events\AuditableEvent;
use App\Models\TaxRate;
use Illuminate\Support\Facades\Gate;

class DeleteTaxRate
{
    public function __invoke(TaxRate $taxRate): void
    {
        Gate::authorize('tax-classes.manage');

        event(new AuditableEvent($taxRate, 'tax_rate.deleted'));

        $taxRate->delete();
    }
}

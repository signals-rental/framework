<?php

namespace App\Actions\TaxClasses;

use App\Events\AuditableEvent;
use App\Models\ProductTaxClass;
use Illuminate\Support\Facades\Gate;

class DeleteProductTaxClass
{
    public function __invoke(ProductTaxClass $taxClass): void
    {
        Gate::authorize('tax-classes.manage');

        event(new AuditableEvent($taxClass, 'product_tax_class.deleted'));

        $taxClass->delete();
    }
}

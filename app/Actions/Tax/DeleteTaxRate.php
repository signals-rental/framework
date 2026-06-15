<?php

namespace App\Actions\Tax;

use App\Events\AuditableEvent;
use App\Models\TaxRate;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class DeleteTaxRate
{
    public function __invoke(TaxRate $taxRate): void
    {
        Gate::authorize('tax-classes.manage');

        $id = $taxRate->id;

        event(new AuditableEvent($taxRate, 'tax_rate.deleted'));

        $taxRate->delete();

        app(WebhookService::class)->dispatch('tax_rate.deleted', [
            'id' => $id,
        ]);
    }
}

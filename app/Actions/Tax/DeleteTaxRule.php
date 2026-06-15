<?php

namespace App\Actions\Tax;

use App\Events\AuditableEvent;
use App\Models\TaxRule;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class DeleteTaxRule
{
    public function __invoke(TaxRule $taxRule): void
    {
        Gate::authorize('tax-classes.manage');

        $id = $taxRule->id;

        event(new AuditableEvent($taxRule, 'tax_rule.deleted'));

        $taxRule->delete();

        app(WebhookService::class)->dispatch('tax_rule.deleted', [
            'id' => $id,
        ]);
    }
}

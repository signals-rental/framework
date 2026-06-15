<?php

namespace App\Actions\Tax;

use App\Data\Tax\CreateTaxRuleData;
use App\Data\Tax\TaxRuleData;
use App\Events\AuditableEvent;
use App\Models\TaxRule;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class CreateTaxRule
{
    public function __invoke(CreateTaxRuleData $data): TaxRuleData
    {
        Gate::authorize('tax-classes.manage');

        $taxRule = TaxRule::create($data->toArray());

        event(new AuditableEvent($taxRule, 'tax_rule.created'));

        app(WebhookService::class)->dispatch('tax_rule.created', [
            'tax_rule' => TaxRuleData::fromModel($taxRule)->toArray(),
        ]);

        return TaxRuleData::fromModel($taxRule);
    }
}

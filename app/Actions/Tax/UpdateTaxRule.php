<?php

namespace App\Actions\Tax;

use App\Data\Tax\TaxRuleData;
use App\Data\Tax\UpdateTaxRuleData;
use App\Events\AuditableEvent;
use App\Models\TaxRule;
use Illuminate\Support\Facades\Gate;

class UpdateTaxRule
{
    public function __invoke(TaxRule $taxRule, UpdateTaxRuleData $data): TaxRuleData
    {
        Gate::authorize('tax-classes.manage');

        $taxRule->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($taxRule, 'tax_rule.updated'));

        return TaxRuleData::fromModel($taxRule->fresh());
    }
}

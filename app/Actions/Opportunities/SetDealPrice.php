<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\SetDealPriceData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\DealPriceSet;

/**
 * Sets a manual deal-total override on an opportunity via the DealPriceSet event.
 *
 * Runs the M7 {@see GuardPipeline} for the `opportunity.set_deal_price` transition
 * (Permission stage replaces the bare Gate::authorize the action used to make).
 *
 * It deliberately does NOT declare `changes_rate`/`changes_tax`: a deal-total
 * override is a STRUCTURAL NET override of the headline, not a re-derivation of the
 * exchange rate or the tax rule. Per
 * {@see App\Services\Opportunities\OpportunityTotalsCalculator} (class docblock,
 * "LOCKING"), the FX/tax lock freezes only later FX-rate or tax-rule re-pricing of
 * the already-agreed basis — structural/price edits on a locked order must STILL
 * recompute the net charge_total (at the frozen exchange-rate snapshot, without
 * re-deriving tax). So {@see App\Guards\Opportunities\Rules\FxTaxLockRule} must NOT
 * block this edit; the calculator's tax_locked branch keeps the tax figures frozen.
 */
class SetDealPrice
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, SetDealPriceData $data): OpportunityData
    {
        $this->commitVerbs(function () use ($opportunity, $data): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.set_deal_price',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            DealPriceSet::fire(
                opportunity_id: $opportunity->state_id,
                deal_total: $data->deal_total,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

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
 * with `changes_rate: true` (the deal price overrides the headline charge), so
 * {@see App\Guards\Opportunities\Rules\FxTaxLockRule} rejects the override (422) on
 * a locked order. The pipeline's Permission stage replaces the bare
 * Gate::authorize the action used to make.
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
                changes: ['changes_rate' => true],
            ));

            DealPriceSet::fire(
                opportunity_id: $opportunity->state_id,
                deal_total: $data->deal_total,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

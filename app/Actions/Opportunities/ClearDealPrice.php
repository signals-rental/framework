<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\DealPriceCleared;

/**
 * Clears a manual deal-total override via the DealPriceCleared event.
 *
 * Runs the M7 {@see GuardPipeline} for the `opportunity.clear_deal_price`
 * transition with `changes_rate: true` (clearing the override reverts the headline
 * charge), so {@see App\Guards\Opportunities\Rules\FxTaxLockRule} rejects the
 * change (422) on a locked order. The pipeline's Permission stage replaces the
 * bare Gate::authorize the action used to make.
 */
class ClearDealPrice
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity): OpportunityData
    {
        $this->commitVerbs(function () use ($opportunity): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.clear_deal_price',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
                changes: ['changes_rate' => true],
            ));

            DealPriceCleared::fire(opportunity_id: $opportunity->state_id);
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

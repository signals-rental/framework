<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\SetDealPriceData;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\DealPriceSet;
use Illuminate\Support\Facades\Gate;

/**
 * Sets a manual deal-total override on an opportunity via the DealPriceSet event.
 */
class SetDealPrice
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, SetDealPriceData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($opportunity, $data): void {
            DealPriceSet::fire(
                opportunity_id: $opportunity->state_id,
                deal_total: $data->deal_total,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

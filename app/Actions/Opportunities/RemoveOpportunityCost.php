<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Models\OpportunityCost;
use App\Verbs\Events\Opportunities\CostRemoved;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a cost from its opportunity via the CostRemoved event.
 */
class RemoveOpportunityCost
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityCost $cost): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $cost->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($cost): void {
            CostRemoved::fire(opportunity_cost_id: $cost->state_id);
        });

        return OpportunityData::fromModel($opportunity->fresh(['costs']));
    }
}

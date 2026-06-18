<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\DealPriceCleared;
use Illuminate\Support\Facades\Gate;

/**
 * Clears a manual deal-total override via the DealPriceCleared event.
 */
class ClearDealPrice
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($opportunity): void {
            DealPriceCleared::fire(opportunity_id: $opportunity->state_id);
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

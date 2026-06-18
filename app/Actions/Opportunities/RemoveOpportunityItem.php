<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemRemoved;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a line item from its opportunity via the ItemRemoved event.
 */
class RemoveOpportunityItem
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item): void {
            ItemRemoved::fire(opportunity_item_id: $item->state_id);
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

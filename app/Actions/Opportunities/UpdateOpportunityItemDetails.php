<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemData;
use App\Data\Opportunities\UpdateOpportunityItemDetailsData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemDetailsUpdated;
use Illuminate\Support\Facades\Gate;

/**
 * Updates a line item's description and warehouse notes via ItemDetailsUpdated.
 */
class UpdateOpportunityItemDetails
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, UpdateOpportunityItemDetailsData $data): OpportunityItemData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($item, $data): void {
            ItemDetailsUpdated::fire(
                opportunity_item_id: $item->state_id,
                description: $data->description,
                notes: $data->notes,
            );
        });

        return OpportunityItemData::fromModel($item->fresh());
    }
}

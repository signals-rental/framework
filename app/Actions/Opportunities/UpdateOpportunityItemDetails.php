<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemData;
use App\Data\Opportunities\UpdateOpportunityItemDetailsData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemDetailsUpdated;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

/**
 * Updates a line item's description and warehouse notes via ItemDetailsUpdated.
 *
 * Only fields present on the DTO are merged over the item's current values;
 * omitted fields are left untouched.
 */
class UpdateOpportunityItemDetails
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, UpdateOpportunityItemDetailsData $data): OpportunityItemData
    {
        Gate::authorize('opportunities.edit');

        $item->refresh();

        $description = $data->description instanceof Optional
            ? $item->description
            : $data->description;

        $notes = $data->notes instanceof Optional
            ? $item->notes
            : $data->notes;

        $this->commitVerbs(function () use ($item, $description, $notes): void {
            ItemDetailsUpdated::fire(
                opportunity_item_id: $item->state_id,
                description: $description,
                notes: $notes,
            );
        });

        return OpportunityItemData::fromModel($item->fresh());
    }
}

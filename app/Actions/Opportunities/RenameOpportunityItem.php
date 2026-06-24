<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemData;
use App\Data\Opportunities\RenameOpportunityItemData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemRenamed;
use Illuminate\Support\Facades\Gate;

/**
 * Renames a line item — a group header or a product/accessory/service line —
 * via the ItemRenamed event.
 */
class RenameOpportunityItem
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, RenameOpportunityItemData $data): OpportunityItemData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($item, $data): void {
            ItemRenamed::fire(
                opportunity_item_id: $item->state_id,
                name: $data->name,
            );
        });

        return OpportunityItemData::fromModel($item->fresh());
    }
}

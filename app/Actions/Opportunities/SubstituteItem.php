<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\SubstituteItemData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemSubstituted;
use Illuminate\Support\Facades\Gate;

/**
 * Substitutes the catalogue item a line refers to via the ItemSubstituted event.
 */
class SubstituteItem
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, SubstituteItemData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data): void {
            ItemSubstituted::fire(
                opportunity_item_id: $item->state_id,
                item_id: $data->item_id,
                item_type: $data->item_type,
                name: $data->name,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

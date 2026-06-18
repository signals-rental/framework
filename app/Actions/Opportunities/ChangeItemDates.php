<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\ChangeItemDatesData;
use App\Data\Opportunities\OpportunityData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemDatesChanged;
use Illuminate\Support\Facades\Gate;

/**
 * Changes a line item's per-item hire window via the ItemDatesChanged event.
 */
class ChangeItemDates
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, ChangeItemDatesData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data): void {
            ItemDatesChanged::fire(
                opportunity_item_id: $item->state_id,
                starts_at: $data->starts_at,
                ends_at: $data->ends_at,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

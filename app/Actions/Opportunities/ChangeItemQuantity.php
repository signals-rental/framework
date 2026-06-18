<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\OpportunityData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemQuantityChanged;
use Illuminate\Support\Facades\Gate;

/**
 * Changes a line item's quantity via the ItemQuantityChanged event.
 */
class ChangeItemQuantity
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, ChangeItemQuantityData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data): void {
            ItemQuantityChanged::fire(
                opportunity_item_id: $item->state_id,
                quantity: $data->quantity,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

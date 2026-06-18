<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemPriceOverridden;
use Illuminate\Support\Facades\Gate;

/**
 * Sets or clears a line item's manual unit-price override via the
 * ItemPriceOverridden event.
 */
class OverrideItemPrice
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, OverrideItemPriceData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data): void {
            ItemPriceOverridden::fire(
                opportunity_item_id: $item->state_id,
                unit_price: $data->unit_price,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemDiscountSet;
use Illuminate\Support\Facades\Gate;

/**
 * Sets or clears a line item's percentage discount via the ItemDiscountSet event.
 */
class SetItemDiscount
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, SetItemDiscountData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data): void {
            ItemDiscountSet::fire(
                opportunity_item_id: $item->state_id,
                discount_percent: $data->discount_percent,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

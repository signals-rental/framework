<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\BulkAdjustData;
use App\Data\Opportunities\OpportunityItemData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\BulkQuantityAdjusted;
use Illuminate\Support\Facades\Gate;

/**
 * Adjusts the requested quantity of a bulk line mid-cycle via the
 * BulkQuantityAdjusted event.
 */
class AdjustBulkQuantity
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, BulkAdjustData $data): OpportunityItemData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($item, $data): void {
            BulkQuantityAdjusted::fire(
                opportunity_item_id: $item->state_id,
                new_quantity: $data->new_quantity,
                reason: $data->reason,
            );
        });

        return OpportunityItemData::fromModel($item->fresh() ?? $item);
    }
}

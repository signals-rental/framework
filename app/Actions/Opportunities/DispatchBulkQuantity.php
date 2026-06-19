<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\OpportunityItemData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\BulkQuantityDispatched;
use Illuminate\Support\Facades\Gate;

/**
 * Records a (partial) dispatch of a bulk line via the BulkQuantityDispatched
 * event.
 */
class DispatchBulkQuantity
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, BulkDispatchData $data): OpportunityItemData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($item, $data): void {
            BulkQuantityDispatched::fire(
                opportunity_item_id: $item->state_id,
                quantity: $data->quantity,
                dispatched_by: $data->dispatched_by,
            );
        });

        return OpportunityItemData::fromModel($item->fresh() ?? $item);
    }
}

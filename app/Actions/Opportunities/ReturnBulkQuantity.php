<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\RebuildsAvailabilitySnapshots;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\BulkReturnData;
use App\Data\Opportunities\OpportunityItemData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\BulkQuantityReturned;
use Illuminate\Support\Facades\Gate;

/**
 * Records a (partial) return of a bulk line via the BulkQuantityReturned event,
 * then rebuilds the line's product/store snapshots so the freed quantity is
 * re-materialised across the horizon.
 */
class ReturnBulkQuantity
{
    use CommitsVerbsEvents, RebuildsAvailabilitySnapshots;

    public function __invoke(OpportunityItem $item, BulkReturnData $data): OpportunityItemData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($item, $data): void {
            BulkQuantityReturned::fire(
                opportunity_item_id: $item->state_id,
                quantity: $data->quantity,
                received_by: $data->received_by,
                condition: $data->condition,
            );
        });

        $fresh = $item->fresh(['opportunity']) ?? $item;

        $this->rebuildSnapshotsForItems([$fresh]);

        return OpportunityItemData::fromModel($fresh);
    }
}

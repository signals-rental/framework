<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\RebuildsAvailabilitySnapshots;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Data\Opportunities\ReturnAssetData;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetReturned;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Checks a single serialised asset back into the warehouse via the AssetReturned
 * event (the RMS `check_in` action). Dispatches a snapshot rebuild for the line's
 * product/store so a contracted (early/overdue) return re-materialises the freed
 * far slots.
 */
class ReturnAsset
{
    use CommitsVerbsEvents, RebuildsAvailabilitySnapshots;

    public function __invoke(OpportunityItemAsset $asset, ReturnAssetData $data): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $returnedAt = $data->returned_at ?? Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($asset, $data, $returnedAt): void {
            AssetReturned::fire(
                state_id: $asset->state_id,
                received_by: $data->received_by,
                return_store_id: $data->return_store_id,
                returned_at: $returnedAt,
            );
        });

        $fresh = $asset->fresh(['item.opportunity', 'stockLevel']) ?? $asset;

        if ($fresh->item !== null) {
            $this->rebuildSnapshotsForItems([$fresh->item]);
        }

        return OpportunityItemAssetData::fromModel($fresh);
    }
}

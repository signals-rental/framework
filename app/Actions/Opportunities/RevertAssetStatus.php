<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\RebuildsAvailabilitySnapshots;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Data\Opportunities\RevertAssetStatusData;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetStatusReverted;
use Illuminate\Support\Facades\Gate;

/**
 * Reverts a serialised asset to an earlier dispatch/return status to correct a
 * mistaken scan, via the AssetStatusReverted event.
 */
class RevertAssetStatus
{
    use CommitsVerbsEvents, RebuildsAvailabilitySnapshots;

    public function __invoke(OpportunityItemAsset $asset, RevertAssetStatusData $data): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($asset, $data): void {
            AssetStatusReverted::fire(
                state_id: $asset->state_id,
                revert_to: $data->revert_to,
                reason: $data->reason,
            );
        });

        $fresh = $asset->fresh(['item.opportunity', 'stockLevel']) ?? $asset;

        if ($fresh->item !== null) {
            $this->rebuildSnapshotsForItems([$fresh->item]);
        }

        return OpportunityItemAssetData::fromModel($fresh);
    }
}

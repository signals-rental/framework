<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetContainerCleared;
use Illuminate\Support\Facades\Gate;

/**
 * Removes an asset assignment from its container via the AssetContainerCleared
 * event.
 */
class ClearAssetContainer
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $stateId = $asset->state_id;

        $this->commitVerbs(function () use ($stateId): void {
            AssetContainerCleared::fire(state_id: $stateId);
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']));
    }
}

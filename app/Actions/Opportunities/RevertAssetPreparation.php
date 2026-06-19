<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetPreparationReverted;
use Illuminate\Support\Facades\Gate;

/**
 * Reverts a prepared asset back to Allocated via the AssetPreparationReverted
 * event.
 */
class RevertAssetPreparation
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $stateId = $asset->state_id;

        $this->commitVerbs(function () use ($stateId): void {
            AssetPreparationReverted::fire(state_id: $stateId);
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']));
    }
}

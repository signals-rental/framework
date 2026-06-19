<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Data\Opportunities\SetAssetContainerData;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetContainerSet;
use Illuminate\Support\Facades\Gate;

/**
 * Nests an asset assignment inside a container stock level via the
 * AssetContainerSet event.
 */
class SetAssetContainer
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset, SetAssetContainerData $data): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $stateId = $asset->state_id;

        $this->commitVerbs(function () use ($stateId, $data): void {
            AssetContainerSet::fire(
                state_id: $stateId,
                container_stock_level_id: $data->container_stock_level_id,
            );
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']));
    }
}

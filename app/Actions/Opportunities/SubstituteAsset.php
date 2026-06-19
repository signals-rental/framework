<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Data\Opportunities\SubstituteAssetData;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetSubstituted;
use Illuminate\Support\Facades\Gate;

/**
 * Swaps the physical asset an assignment points at via the AssetSubstituted event,
 * preserving the assignment's current status.
 */
class SubstituteAsset
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset, SubstituteAssetData $data): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $stateId = $asset->state_id;

        $this->commitVerbs(function () use ($stateId, $data): void {
            AssetSubstituted::fire(
                state_id: $stateId,
                new_stock_level_id: $data->new_stock_level_id,
                reason: $data->reason,
            );
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']));
    }
}

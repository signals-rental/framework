<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetOnHire;
use Illuminate\Support\Facades\Gate;

/**
 * Confirms a dispatched asset is now on hire with the client via the AssetOnHire
 * event.
 */
class MarkAssetOnHire
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($asset): void {
            AssetOnHire::fire(state_id: $asset->state_id);
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']) ?? $asset);
    }
}

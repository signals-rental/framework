<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetPrepared;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Marks an allocated asset as prepared via the AssetPrepared event.
 */
class PrepareAsset
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $stateId = $asset->state_id;
        $preparedAt = Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($stateId, $preparedAt): void {
            AssetPrepared::fire(state_id: $stateId, prepared_at: $preparedAt);
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']));
    }
}

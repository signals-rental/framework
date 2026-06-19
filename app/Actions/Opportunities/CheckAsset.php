<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\CheckAssetData;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetChecked;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Records the condition assessment for a returned asset via the AssetChecked
 * event (the RMS `finalise_check_in` action).
 */
class CheckAsset
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset, CheckAssetData $data): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $checkedAt = $data->checked_at ?? Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($asset, $data, $checkedAt): void {
            AssetChecked::fire(
                state_id: $asset->state_id,
                condition: $data->condition,
                checked_by: $data->checked_by,
                damage_notes: $data->damage_notes,
                checked_at: $checkedAt,
            );
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']) ?? $asset);
    }
}

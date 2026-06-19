<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetDispatched;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Books a single serialised asset out of the warehouse via the AssetDispatched
 * event (the RMS `book_out` action).
 */
class DispatchAsset
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset, DispatchAssetData $data): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $dispatchedAt = $data->dispatched_at ?? Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($asset, $data, $dispatchedAt): void {
            AssetDispatched::fire(
                state_id: $asset->state_id,
                dispatched_by: $data->dispatched_by,
                vehicle_id: $data->vehicle_id,
                notes: $data->notes,
                dispatched_at: $dispatchedAt,
            );
        });

        return OpportunityItemAssetData::fromModel(
            $asset->fresh(['stockLevel']) ?? $asset,
        );
    }
}

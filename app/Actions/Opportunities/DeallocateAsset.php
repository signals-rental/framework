<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetDeallocated;
use Illuminate\Support\Facades\Gate;

/**
 * Releases an allocated/prepared asset from its line item via the AssetDeallocated
 * event. The projection row is removed, so there is nothing to return.
 */
class DeallocateAsset
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset, ?string $reason = null): void
    {
        Gate::authorize('opportunities.edit');

        $stateId = $asset->state_id;

        $this->commitVerbs(function () use ($stateId, $reason): void {
            AssetDeallocated::fire(
                state_id: $stateId,
                reason: $reason,
            );
        });
    }
}

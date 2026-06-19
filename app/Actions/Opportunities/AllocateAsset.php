<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\AssetAllocated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Allocates a single serialised asset to an opportunity line item via the
 * AssetAllocated genesis event, allocating the replay-stable assignment id, firing
 * the event, and committing it with its projection atomically.
 */
class AllocateAsset
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, AllocateAssetData $data): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $assignmentId = null;
        $allocatedAt = Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($item, $data, $allocatedAt, &$assignmentId): void {
            // Allocate the replay-stable small PK and bake it into the event so a
            // truncate + Verbs::replay() rebuild reproduces the identical id.
            $assignmentId = app(SequenceAllocator::class)->next('opportunity_item_assets');

            AssetAllocated::fire(
                assignment_id: $assignmentId,
                opportunity_item_id: $item->id,
                stock_level_id: $data->stock_level_id,
                allocated_at: $allocatedAt,
            );
        });

        $asset = OpportunityItemAsset::query()->whereKey($assignmentId)->with('stockLevel')->firstOrFail();

        return OpportunityItemAssetData::fromModel($asset);
    }
}

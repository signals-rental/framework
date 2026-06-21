<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\ResolvesOpportunityAssets;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\QuickPrepareAssetsData;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\AssetPrepared;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Prepares (picks/packs) several allocated assets of one opportunity in a SINGLE
 * atomic Verbs commit (the RMS `quick_prepare` action, opportunity-lifecycle.md
 * §11.1).
 *
 * Every {@see AssetPrepared} event fires inside one
 * {@see CommitsVerbsEvents::commitVerbs()} boundary, so a single failure (an asset
 * not in the Allocated status) rolls back the whole batch — no partial preparation
 * is left behind. All assets must belong to line items of the bound opportunity.
 *
 * Preparation does not change which assets a line claims and is not part of the
 * §7.6 aggregate auto-promotion axis (an order does not promote off Active until an
 * asset is dispatched), so — unlike {@see QuickBookOut} / {@see QuickCheckIn} —
 * there is no promotion to coordinate across the batch.
 */
class QuickPrepareAssets
{
    use CommitsVerbsEvents, ResolvesOpportunityAssets;

    public function __invoke(Opportunity $opportunity, QuickPrepareAssetsData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $preparedAt = $data->prepared_at ?? Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($opportunity, $data, $preparedAt): void {
            foreach ($data->asset_ids as $assetId) {
                $asset = $this->assetForOpportunity((int) $assetId, $opportunity);

                AssetPrepared::fire(
                    state_id: $asset->state_id,
                    prepared_at: $preparedAt,
                );
            }
        });

        return OpportunityData::fromModel($opportunity->fresh(['items', 'items.assets']) ?? $opportunity);
    }
}

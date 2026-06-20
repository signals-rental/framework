<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Enums\ShortageDispatchPolicy;
use App\Models\OpportunityItemAsset;
use App\Services\Shortages\DispatchShortageGate;
use App\ValueObjects\DispatchGateResult;
use App\Verbs\Events\Opportunities\AssetDispatched;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Books a single serialised asset out of the warehouse via the AssetDispatched
 * event (the RMS `book_out` action).
 *
 * Before firing, the {@see DispatchShortageGate} enforces the store's
 * {@see ShortageDispatchPolicy} on the asset's line (§7.4): Block →
 * 422; WarnPartial → proceed and expose held-item metadata via {@see $gateResult};
 * AllowPartial → proceed silently. The gate runs inside the same atomic commit, so
 * a Block leaves nothing dispatched.
 */
class DispatchAsset
{
    use CommitsVerbsEvents;

    /**
     * The dispatch-gate outcome from the last invocation — the controller reads it
     * to surface held-item metadata on a WarnPartial dispatch. Null until invoked.
     */
    public ?DispatchGateResult $gateResult = null;

    public function __invoke(OpportunityItemAsset $asset, DispatchAssetData $data): OpportunityItemAssetData
    {
        Gate::authorize('opportunities.edit');

        $dispatchedAt = $data->dispatched_at ?? Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($asset, $data, $dispatchedAt): void {
            $item = $asset->item()->first();

            if ($item !== null) {
                // §7.4 dispatch gate — Block throws (rolling back the commit);
                // WarnPartial/AllowPartial proceed (held-item meta captured below).
                $this->gateResult = app(DispatchShortageGate::class)->enforceForItem($item);
            }

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

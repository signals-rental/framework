<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\QuickBookOutData;
use App\Enums\AssetAssignmentStatus;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use App\Services\Opportunities\AutoPromotionContext;
use App\Verbs\Events\Opportunities\AssetDispatched;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use App\Verbs\Events\Opportunities\OpportunityStatusPromoted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Books several serialised assets out of one opportunity in a SINGLE atomic Verbs
 * commit (the RMS `quick_book_out` action, opportunity-lifecycle.md §11.1).
 *
 * Every {@see AssetDispatched} event fires inside one
 * {@see CommitsVerbsEvents::commitVerbs()} boundary, so any single failure (asset
 * not allocated, opportunity not an Order) rolls back the whole batch. All assets
 * must belong to line items of the bound opportunity.
 *
 * Auto-promotion consistency: because every event's `fired()` hook runs before any
 * handle() in a batch commit, the per-event promotion can only see each asset in
 * isolation. This wrapper therefore fires ONE final authoritative
 * {@see OpportunityStatusPromoted} with the WHOLE
 * batch overlaid, so the order lands on the correct aggregate status (e.g. On Hire
 * once the last asset is out) in a single consistent step.
 */
class QuickBookOut
{
    use CommitsVerbsEvents, PromotesOpportunityStatus;

    public function __invoke(Opportunity $opportunity, QuickBookOutData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $dispatchedAt = $data->dispatched_at ?? Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($opportunity, $data, $dispatchedAt): void {
            $overlay = app(AutoPromotionContext::class)->suppress(function () use ($opportunity, $data, $dispatchedAt): array {
                $overlay = [];

                foreach ($data->asset_ids as $assetId) {
                    $asset = $this->assetForOpportunity((int) $assetId, $opportunity);

                    AssetDispatched::fire(
                        state_id: $asset->state_id,
                        dispatched_by: $data->dispatched_by,
                        vehicle_id: $data->vehicle_id,
                        dispatched_at: $dispatchedAt,
                    );

                    $overlay[$asset->id] = AssetAssignmentStatus::Dispatched;
                }

                return $overlay;
            });

            // One final, batch-complete promotion (outside suppression) so the
            // aggregate status reflects every asset just booked out.
            $this->promoteOpportunityFromItems(
                $opportunity->fresh(),
                ['assignment_statuses' => $overlay],
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items', 'items.assets']) ?? $opportunity);
    }

    private function assetForOpportunity(int $assetId, Opportunity $opportunity): OpportunityItemAsset
    {
        $asset = OpportunityItemAsset::query()->whereKey($assetId)->with('item')->first();

        if ($asset === null || $asset->item === null || $asset->item->opportunity_id !== $opportunity->id) {
            throw new NotFoundHttpException('An asset in the batch does not belong to the opportunity.');
        }

        return $asset;
    }
}

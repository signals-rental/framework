<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\RebuildsAvailabilitySnapshots;
use App\Actions\Opportunities\Concerns\ResolvesOpportunityAssets;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\QuickCheckInData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Models\Opportunity;
use App\Services\Opportunities\AutoPromotionContext;
use App\Verbs\Events\Opportunities\AssetChecked;
use App\Verbs\Events\Opportunities\AssetReturned;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Checks several serialised assets back into one opportunity in a SINGLE atomic
 * Verbs commit (the RMS `quick_check_in` action, opportunity-lifecycle.md §11.1).
 *
 * Each asset fires an {@see AssetReturned}; when `finalise` is set it is immediately
 * followed by an {@see AssetChecked} (condition Good) so the check-in queue is
 * cleared in one pass (the RMS `finalise_check_in`). Any failure rolls back the
 * whole batch.
 *
 * Auto-promotion consistency mirrors {@see QuickBookOut}: a single final
 * authoritative promotion is fired with the whole batch overlaid so the order lands
 * on the correct aggregate (Returned, or Checked when finalising) in one step.
 * A snapshot rebuild is dispatched once per distinct product/store touched.
 */
class QuickCheckIn
{
    use CommitsVerbsEvents, PromotesOpportunityStatus, RebuildsAvailabilitySnapshots, ResolvesOpportunityAssets;

    public function __invoke(Opportunity $opportunity, QuickCheckInData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $returnedAt = $data->returned_at ?? Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($opportunity, $data, $returnedAt): void {
            $overlay = app(AutoPromotionContext::class)->suppress(function () use ($opportunity, $data, $returnedAt): array {
                $overlay = [];

                foreach ($data->asset_ids as $assetId) {
                    $asset = $this->assetForOpportunity((int) $assetId, $opportunity);

                    AssetReturned::fire(
                        state_id: $asset->state_id,
                        received_by: $data->received_by,
                        return_store_id: $data->return_store_id,
                        returned_at: $returnedAt,
                    );

                    if ($data->finalise) {
                        AssetChecked::fire(
                            state_id: $asset->state_id,
                            condition: AssetCondition::Good->value,
                            checked_by: $data->received_by,
                            checked_at: $returnedAt,
                        );
                    }

                    $overlay[$asset->id] = $data->finalise
                        ? AssetAssignmentStatus::Finalised
                        : AssetAssignmentStatus::CheckedIn;
                }

                return $overlay;
            });

            $this->promoteOpportunityFromItems(
                $opportunity->fresh(),
                ['assignment_statuses' => $overlay],
            );
        });

        $fresh = $opportunity->fresh(['items', 'items.assets']) ?? $opportunity;

        $this->rebuildSnapshotsForItems($fresh->items);

        return OpportunityData::fromModel($fresh);
    }
}

<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\AssetAssignmentStatus;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\Concerns\AdjustsStockAllocation;
use App\Verbs\Events\Opportunities\Concerns\GuardsAssetAssignment;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\Events\Opportunities\Concerns\SyncsAssetDemands;
use App\Verbs\States\AssetAssignmentState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Checks a serialised asset back into the warehouse (opportunity-lifecycle.md
 * §5.4 / §7.3). Valid only from Dispatched or On Hire.
 *
 * Availability releases on scan, with NO finalisation gate (availability-engine
 * "Availability Update Timing on Return"): the asset demand's `ends_at` is set to
 * the ACTUAL return time, the phase moves Operational → Closed (is_active false),
 * and the period is recomputed with the turnaround buffer off the actual return.
 * The unit's `quantity_allocated` is released — it is physically back.
 *
 * Auto-promotion (§7.6): once the last out asset returns the order is promoted to
 * Returned (or On Hire if some are still out). The promotion fires from
 * {@see fired()}, once, replay-safely.
 */
class AssetReturned extends Event
{
    use AdjustsStockAllocation, GuardsAssetAssignment, PromotesOpportunityStatus, RecordsOpportunityAudit, SyncsAssetDemands;

    public function __construct(
        #[StateId(AssetAssignmentState::class)]
        public int $state_id,
        public ?int $received_by = null,
        public ?int $return_store_id = null,
        public ?string $returned_at = null,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        $this->assertAssignmentMutable($state);
        $this->assertStatusIn(
            $state,
            [AssetAssignmentStatus::Dispatched, AssetAssignmentStatus::OnHire],
            'Only a dispatched or on-hire asset can be returned.',
        );
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->status = AssetAssignmentStatus::CheckedIn->value;
        $state->returned_at = $this->returnedAt();
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(AssetAssignmentState $state): void
    {
        $asset = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first();

        if ($asset === null) {
            return;
        }

        $previousStatus = $asset->status->value;

        $asset->forceFill([
            'status' => AssetAssignmentStatus::CheckedIn->value,
            'returned_at' => $state->returned_at,
        ])->saveQuietly();

        // Persist a per-line return-store override when supplied (consumed for
        // reporting / future return-store routing).
        $item = $asset->item()->first();

        if ($item !== null && $this->return_store_id !== null && $item->return_store_id === null) {
            $item->forceFill(['return_store_id' => $this->return_store_id])->saveQuietly();
            $item->refresh();
        }

        // The asset is physically back — release its committed allocation.
        $this->decrementStockAllocation((int) $state->stock_level_id, '1');

        if ($item !== null) {
            // Resync the line's demands: the resolver now closes this asset's demand
            // at the actual return time (phase Closed) from the projected returned_at,
            // so the recompute is reproduced on any later resync and on replay.
            $this->syncAssetDemands($item);
        }

        $opportunity = $item?->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_returned',
                newValues: [
                    'assignment_id' => $state->assignment_id,
                    'received_by' => $this->received_by,
                    'return_store_id' => $this->return_store_id,
                    'returned_at' => Carbon::parse($state->returned_at)->toIso8601String(),
                ],
                oldValues: ['status' => $previousStatus],
            );
        }
    }

    public function fired(AssetAssignmentState $state): void
    {
        $opportunity = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first()
            ?->item()->first()
            ?->opportunity()->first();

        $this->promoteOpportunityFromItems(
            $opportunity,
            $this->singleAssetOverlay($state->assignment_id, AssetAssignmentStatus::CheckedIn),
        );
    }

    private function returnedAt(): CarbonImmutable
    {
        return $this->returned_at !== null
            ? CarbonImmutable::parse($this->returned_at)
            : CarbonImmutable::now();
    }
}

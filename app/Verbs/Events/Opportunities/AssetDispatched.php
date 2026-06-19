<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\AssetAssignmentStatus;
use App\Enums\OpportunityState;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
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
 * Books a serialised asset out of the warehouse (opportunity-lifecycle.md §5.4 /
 * §7.2). Valid only from Allocated/Prepared AND only when the parent opportunity
 * is an ORDER — this is the Order-only fulfilment guard (allocation, by contrast,
 * is permitted from Reserved quotes; see {@see AssetAllocated}).
 *
 * Demand effect: the asset's demand phase advances Committed → Operational. If the
 * asset leaves before its planned start, the asset demand's `starts_at` is pulled
 * back to the actual dispatch time and the period recomputed (availability-engine
 * "Asset-Level Date Tracking"). The unit stays allocated (it is out, not back).
 *
 * Auto-promotion: after projecting, the opportunity's aggregate fulfilment status
 * is re-derived (§7.6) and an {@see OpportunityStatusPromoted} event fires from
 * {@see fired()} when it changes — once, replay-safely (fired() never runs on
 * replay).
 */
class AssetDispatched extends Event
{
    use GuardsAssetAssignment, PromotesOpportunityStatus, RecordsOpportunityAudit, SyncsAssetDemands;

    public function __construct(
        #[StateId(AssetAssignmentState::class)]
        public int $state_id,
        public ?int $dispatched_by = null,
        public ?int $vehicle_id = null,
        public ?string $notes = null,
        public ?string $dispatched_at = null,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        $this->assertAssignmentMutable($state);
        $this->assertStatusIn(
            $state,
            [AssetAssignmentStatus::Allocated, AssetAssignmentStatus::Prepared],
            'Only an allocated or prepared asset can be dispatched.',
        );

        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();
        $opportunity = $item?->opportunity()->first();

        $this->assert(
            $opportunity !== null && $opportunity->statusEnum()->state() === OpportunityState::Order,
            'Assets can only be dispatched on an order.',
        );
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->status = AssetAssignmentStatus::Dispatched->value;
        $state->dispatched_at = $this->dispatchedAt();
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(AssetAssignmentState $state): void
    {
        $asset = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first();

        if ($asset === null) {
            return;
        }

        $asset->forceFill([
            'status' => AssetAssignmentStatus::Dispatched->value,
            'dispatched_at' => $state->dispatched_at,
        ])->saveQuietly();

        $item = $asset->item()->first();

        if ($item === null) {
            return;
        }

        // Resync the line's demands so the asset-specific demand picks up the
        // Operational phase from the (now-promoted) opportunity status; the resolver
        // also pulls the start back to the actual dispatch time when the asset left
        // before its planned start (reproduced on any later resync and on replay).
        $this->syncAssetDemands($item);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_dispatched',
                newValues: [
                    'assignment_id' => $state->assignment_id,
                    'dispatched_by' => $this->dispatched_by,
                    'vehicle_id' => $this->vehicle_id,
                    'dispatched_at' => Carbon::parse($state->dispatched_at)->toIso8601String(),
                ],
                oldValues: ['status' => AssetAssignmentStatus::Allocated->value],
            );
        }
    }

    /**
     * Auto-promote the parent opportunity (§7.6) from the post-projection item
     * aggregate. Runs only in the original request, so the promotion persists as
     * its own replayable {@see OpportunityStatusPromoted} event.
     */
    public function fired(AssetAssignmentState $state): void
    {
        $opportunity = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first()
            ?->item()->first()
            ?->opportunity()->first();

        $this->promoteOpportunityFromItems(
            $opportunity,
            $this->singleAssetOverlay($state->assignment_id, AssetAssignmentStatus::Dispatched),
        );
    }

    private function dispatchedAt(): CarbonImmutable
    {
        return $this->dispatched_at !== null
            ? CarbonImmutable::parse($this->dispatched_at)
            : CarbonImmutable::now();
    }
}

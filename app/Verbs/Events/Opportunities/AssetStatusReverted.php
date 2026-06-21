<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\AssetAssignmentStatus;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\Concerns\AdjustsStockAllocation;
use App\Verbs\Events\Opportunities\Concerns\GuardsAssetAssignment;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\Events\Opportunities\Concerns\SyncsAssetDemands;
use App\Verbs\States\AssetAssignmentState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Reverts an asset to an EARLIER status in the dispatch/return cycle to correct a
 * mistaken scan (opportunity-lifecycle.md §5.4 — `AssetStatusReverted`). The
 * target status must be strictly behind the current one.
 *
 * Reversing the effects of the steps being undone:
 *
 *  - Stock: `quantity_allocated` is released the moment an asset is checked back
 *    in. Reverting from a returned state (CheckedIn/Finalised) back to an out
 *    state (Dispatched/OnHire) RE-claims the unit (+1). Reverting between out
 *    states, or between committed states, leaves allocation unchanged.
 *  - Demand: the asset's demand window/phase is rebuilt from the line via
 *    {@see SyncsAssetDemands} so it follows the (re-derived) opportunity status;
 *    its milestone timestamps ahead of the target are cleared.
 *
 * Auto-promotion re-derivation runs in {@see fired()} so the opportunity status
 * can move BACK (e.g. Returned → On Hire) when the revert changes the aggregate —
 * persisted as its own replayable {@see OpportunityStatusPromoted} event.
 */
class AssetStatusReverted extends Event
{
    use AdjustsStockAllocation, GuardsAssetAssignment, PromotesOpportunityStatus, RecordsOpportunityAudit, SyncsAssetDemands;

    public function __construct(
        #[StateId(AssetAssignmentState::class)]
        public int $state_id,
        public int $revert_to = 0,
        public ?string $reason = null,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        // Reject reverting a deallocated/removed assignment: AssetDeallocated
        // hard-deletes the projection row, so without this the state would mutate
        // with no row to write to and diverge permanently (ghost state on replay).
        $this->assertAssignmentNotRemoved($state);

        $this->assertAssignmentMutable($state);

        $target = AssetAssignmentStatus::tryFrom($this->revert_to);

        $this->assert($target !== null, 'The revert target is not a valid asset status.');

        $this->assert(
            $target !== null && $this->revert_to < $state->status,
            'An asset can only be reverted to an earlier status.',
        );
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->status = $this->revert_to;

        // Clear milestone timestamps for the steps being undone so a later replay
        // of the forward events repopulates them cleanly.
        if ($this->revert_to < AssetAssignmentStatus::Dispatched->value) {
            $state->dispatched_at = null;
        }

        if ($this->revert_to < AssetAssignmentStatus::CheckedIn->value) {
            $state->returned_at = null;
        }

        if ($this->revert_to < AssetAssignmentStatus::Finalised->value) {
            $state->checked_at = null;
            $state->condition_on_return = null;
        }

        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(AssetAssignmentState $state): void
    {
        $asset = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first();

        if ($asset === null) {
            return;
        }

        // The projection row still carries the PRE-revert status here (handle runs
        // before this forceFill), so it is the authoritative source for what is
        // being undone — replay-stable, independent of in-memory state ordering.
        $previousStatus = $asset->status->value;
        $target = $this->revert_to;

        $asset->forceFill([
            'status' => $target,
            'dispatched_at' => $target < AssetAssignmentStatus::Dispatched->value ? null : $state->dispatched_at,
            'returned_at' => $target < AssetAssignmentStatus::CheckedIn->value ? null : $state->returned_at,
            'checked_at' => $target < AssetAssignmentStatus::Finalised->value ? null : $state->checked_at,
            'condition_on_return' => $target < AssetAssignmentStatus::Finalised->value ? null : $state->condition_on_return,
        ])->saveQuietly();

        // Re-claim the allocation when crossing back from a returned state into an
        // out state (the return had released it).
        $wasReturned = $previousStatus >= AssetAssignmentStatus::CheckedIn->value;
        $nowOut = $target >= AssetAssignmentStatus::Allocated->value && $target < AssetAssignmentStatus::CheckedIn->value;

        if ($wasReturned && $nowOut) {
            $this->incrementStockAllocation((int) $state->stock_level_id, '1');
        }

        $item = $asset->item()->first();

        if ($item instanceof OpportunityItem) {
            // Rebuild the line's demands so the reverted asset's demand window/phase
            // follows the (re-derived) opportunity status.
            $this->syncAssetDemands($item);
        }

        $opportunity = $item?->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_status_reverted',
                newValues: ['assignment_id' => $state->assignment_id, 'status' => $target, 'reason' => $this->reason],
                oldValues: ['status' => $previousStatus],
            );
        }
    }

    public function fired(AssetAssignmentState $state): void
    {
        $this->promoteOpportunityFromItems(
            $this->opportunityForAssignment($state->assignment_id),
            $this->singleAssetOverlay($state->assignment_id, AssetAssignmentStatus::from($this->revert_to)),
        );
    }
}

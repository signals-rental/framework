<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\AssetAssignmentStatus;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\Concerns\AdjustsStockAllocation;
use App\Verbs\Events\Opportunities\Concerns\GuardsAssetAssignment;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\Events\Opportunities\Concerns\SyncsAssetDemands;
use App\Verbs\States\AssetAssignmentState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Releases a previously-allocated asset from its line item
 * (opportunity-lifecycle.md §5.4). Only valid while the assignment is Allocated or
 * Prepared (a dispatched/on-hire/returned asset cannot simply be deallocated).
 *
 * Marks the assignment state removed, hard-deletes the `opportunity_item_assets`
 * row, decrements the stock level's `quantity_allocated`, and re-syncs the line's
 * demands so the freed unit reverts to a quantity-based demand (the reverse of the
 * §9.3 serialised demand transition).
 */
class AssetDeallocated extends Event
{
    use AdjustsStockAllocation, GuardsAssetAssignment, RecordsOpportunityAudit, SyncsAssetDemands;

    public function __construct(
        #[StateId(AssetAssignmentState::class)]
        public int $state_id,
        public ?string $reason = null,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        $this->assertAssignmentMutable($state);
        $this->assertStatusIn(
            $state,
            [AssetAssignmentStatus::Allocated, AssetAssignmentStatus::Prepared],
            'Only an allocated or prepared asset can be deallocated.',
        );
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->status = AssetAssignmentStatus::Finalised->value;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(AssetAssignmentState $state): void
    {
        $asset = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first();

        if ($asset === null) {
            return;
        }

        $stockLevelId = $asset->stock_level_id;
        $item = $asset->item()->first();

        $asset->delete();

        $this->decrementStockAllocation($stockLevelId, '1');

        if ($item === null) {
            return;
        }

        $this->syncAssetDemands($item);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_deallocated',
                newValues: ['reason' => $this->reason],
                oldValues: [
                    'assignment_id' => $state->assignment_id,
                    'stock_level_id' => $stockLevelId,
                ],
            );
        }
    }
}

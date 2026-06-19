<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\StockMethod;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\StockLevel;
use App\Verbs\Events\Opportunities\Concerns\AdjustsStockAllocation;
use App\Verbs\Events\Opportunities\Concerns\GuardsAssetAssignment;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\Events\Opportunities\Concerns\SyncsAssetDemands;
use App\Verbs\States\AssetAssignmentState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Swaps the physical asset an assignment points at (opportunity-lifecycle.md
 * §5.4): the old asset is released and a new one takes its place while the
 * assignment PRESERVES its current status (an allocated swap stays allocated, a
 * prepared swap stays prepared).
 *
 * The old stock level's `quantity_allocated` is decremented and the new one's
 * incremented; the line's demands are re-synced so the asset-specific demand moves
 * from the old asset to the new. The new asset must belong to the same product, be
 * serialised, and be free for the line's window.
 */
class AssetSubstituted extends Event
{
    use AdjustsStockAllocation, GuardsAssetAssignment, RecordsOpportunityAudit, SyncsAssetDemands;

    public function __construct(
        #[StateId(AssetAssignmentState::class)]
        public int $state_id,
        public int $new_stock_level_id = 0,
        public ?string $reason = null,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        $this->assertAssignmentMutable($state);

        $this->assert(
            $this->new_stock_level_id !== $state->stock_level_id,
            'The substitute asset is the same as the current asset.',
        );

        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();
        $stockLevel = StockLevel::query()->whereKey($this->new_stock_level_id)->first();

        $this->assert($stockLevel !== null, 'The substitute stock level does not exist.');

        $this->assert(
            $stockLevel !== null && $item !== null && $stockLevel->product_id === $item->item_id,
            'The substitute stock level does not belong to the line item\'s product.',
        );

        $this->assert(
            $stockLevel?->product?->stock_method === StockMethod::Serialised,
            'Only serialised stock can be substituted in.',
        );

        $this->assert(
            ! OpportunityItemAsset::query()
                ->where('opportunity_item_id', $state->opportunity_item_id)
                ->where('stock_level_id', $this->new_stock_level_id)
                ->exists(),
            'The substitute asset is already allocated to the line item.',
        );

        if ($item !== null && $stockLevel !== null) {
            $this->assertAssetAvailableForItem($item, $this->new_stock_level_id);
        }
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->stock_level_id = $this->new_stock_level_id;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(AssetAssignmentState $state): void
    {
        $asset = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first();

        if ($asset === null) {
            return;
        }

        $oldStockLevelId = $asset->stock_level_id;

        $asset->forceFill([
            'stock_level_id' => $state->stock_level_id,
        ])->saveQuietly();

        $this->decrementStockAllocation($oldStockLevelId, '1');
        $this->incrementStockAllocation($state->stock_level_id, '1');

        $item = $asset->item()->first();

        if ($item === null) {
            return;
        }

        $this->syncAssetDemands($item);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_substituted',
                newValues: [
                    'assignment_id' => $state->assignment_id,
                    'stock_level_id' => $state->stock_level_id,
                    'reason' => $this->reason,
                ],
                oldValues: ['stock_level_id' => $oldStockLevelId],
            );
        }
    }
}

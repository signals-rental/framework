<?php

namespace App\Verbs\Events\Opportunities;

use App\Actions\Opportunities\AllocateAsset;
use App\Enums\AssetAssignmentStatus;
use App\Enums\StockMethod;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Verbs\Events\Opportunities\Concerns\AdjustsStockAllocation;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\Events\Opportunities\Concerns\SyncsAssetDemands;
use App\Verbs\States\AssetAssignmentState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Genesis event for a per-asset assignment: pins a specific serialised physical
 * asset (`stock_level_id`) to an opportunity line item (opportunity-lifecycle.md
 * §5.4). Creates the assignment state, projects the `opportunity_item_assets`
 * row, increments the stock level's `quantity_allocated`, and re-syncs the line's
 * availability demands so the serialised demand transition (§9.3) applies — the
 * line's quantity-based demand shrinks by one and an asset-specific demand row
 * appears.
 *
 * `assignment_id` is the application-allocated small projection PK, allocated by
 * the {@see AllocateAsset} action via the
 * SequenceAllocator and baked into the payload so a truncate + Verbs::replay()
 * rebuild reproduces identical ids (replay-stable).
 *
 * PRECONDITION (§5.4): allocation requires the parent opportunity to be open
 * (not closed/terminal) and the line item to be mutable, the referenced stock
 * level to belong to the line's product and be serialised, and that asset to be
 * free for the line's window. It does NOT require the Order state — allocation is
 * permitted from Reserved quotes and Active orders alike (both place Committed
 * demand). The Order-only constraint belongs to dispatch (`AssetDispatched`, M5-2).
 */
class AssetAllocated extends Event
{
    use AdjustsStockAllocation, RecordsOpportunityAudit, SyncsAssetDemands;

    public function __construct(
        public int $assignment_id,
        #[StateId(AssetAssignmentState::class)]
        public ?int $state_id = null,
        public int $opportunity_item_id = 0,
        public int $stock_level_id = 0,
        public ?string $allocated_at = null,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        $item = OpportunityItem::query()->whereKey($this->opportunity_item_id)->first();

        $this->assert($item !== null, 'The line item does not exist.');

        $opportunity = $item?->opportunity()->first();

        $this->assert(
            $opportunity !== null && ! $opportunity->statusEnum()->isClosed(),
            'A closed opportunity\'s assets cannot be allocated.',
        );

        $stockLevel = StockLevel::query()->whereKey($this->stock_level_id)->first();

        $this->assert($stockLevel !== null, 'The stock level does not exist.');

        $this->assert(
            $stockLevel !== null && $item !== null && $stockLevel->product_id === $item->itemable_id,
            'The stock level does not belong to the line item\'s product.',
        );

        $this->assert(
            $stockLevel?->product?->stock_method === StockMethod::Serialised,
            'Only serialised stock can be allocated as a specific asset.',
        );

        // The same physical asset must not already be allocated to this line.
        $this->assert(
            ! OpportunityItemAsset::query()
                ->where('opportunity_item_id', $this->opportunity_item_id)
                ->where('stock_level_id', $this->stock_level_id)
                ->exists(),
            'This asset is already allocated to the line item.',
        );

        // Over-allocation count guard: the number of physical assets allocated to a
        // line may not exceed its requested quantity. Counts the already-projected
        // active allocations + this one. Within a SINGLE batch commit (quick_allocate
        // fires N AssetAllocated events before any projects) in-flight siblings are
        // not yet visible here, so the batch action enforces the running count too —
        // see QuickAllocateAssets.
        if ($item !== null) {
            $existing = OpportunityItemAsset::query()
                ->where('opportunity_item_id', $this->opportunity_item_id)
                ->count();

            $this->assert(
                $existing + 1 <= (int) ceil((float) $item->quantity),
                'Allocating this asset would exceed the line item\'s quantity.',
            );
        }

        // Over-allocation guard: the asset must be free for the line's window.
        if ($item !== null && $stockLevel !== null) {
            $this->assertAssetAvailableForItem($item, $this->stock_level_id);
        }
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->assignment_id = $this->assignment_id;
        $state->opportunity_item_id = $this->opportunity_item_id;
        $state->stock_level_id = $this->stock_level_id;
        $state->status = AssetAssignmentStatus::Allocated->value;
        $state->container_stock_level_id = null;
        $state->allocated_at = $this->allocatedAt();
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(AssetAssignmentState $state): void
    {
        OpportunityItemAsset::query()->updateOrCreate(
            ['id' => $state->assignment_id],
            [
                'state_id' => $state->id,
                'opportunity_item_id' => $state->opportunity_item_id,
                'stock_level_id' => $state->stock_level_id,
                'status' => AssetAssignmentStatus::Allocated->value,
                'container_stock_level_id' => null,
                'allocated_at' => $state->allocated_at,
            ],
        );

        $this->incrementStockAllocation($state->stock_level_id, '1');

        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $this->syncAssetDemands($item);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_allocated',
                newValues: [
                    'assignment_id' => $state->assignment_id,
                    'opportunity_item_id' => $state->opportunity_item_id,
                    'stock_level_id' => $state->stock_level_id,
                ],
                oldValues: null,
            );
        }
    }

    private function allocatedAt(): CarbonImmutable
    {
        return $this->allocated_at !== null
            ? CarbonImmutable::parse($this->allocated_at)
            : CarbonImmutable::now();
    }
}

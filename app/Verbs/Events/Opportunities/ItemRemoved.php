<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityItems;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Removes a line item from its opportunity. Releases the item's availability
 * demand, hard-deletes the projection row (there is no soft delete on
 * `opportunity_items`), and rolls the parent totals back down.
 */
class ItemRemoved extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);

        // Removal must not strand committed fulfilment. Mirroring the
        // {@see ItemQuantityChanged} guard: a line with serialised assets still
        // allocated to it, or with bulk quantity already dispatched, cannot be
        // removed — the assets must be deallocated / returned first. Both are read
        // from the projection (validate() runs before handle(), so the rows
        // reflect the pre-removal state) and replay-safely reproduce.
        $allocatedAssets = OpportunityItemAsset::query()
            ->where('opportunity_item_id', $state->opportunity_item_id)
            ->count();

        $this->assert(
            $allocatedAssets === 0,
            sprintf(
                'Cannot remove a line with %d allocated asset(s); deallocate first.',
                $allocatedAssets,
            ),
        );

        $dispatched = (int) round((float) $state->dispatched_quantity);

        $this->assert(
            $dispatched === 0,
            sprintf(
                'Cannot remove a line with %d already-dispatched unit(s); return them first.',
                $dispatched,
            ),
        );
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->is_removed = true;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $opportunity = $item->opportunity()->first();
        $snapshot = $this->itemSnapshot($item);

        // Release the demands while the row still exists, then delete it.
        $this->releaseDemand($item);
        $item->delete();

        if ($opportunity !== null) {
            $this->rollUpOnly($opportunity);

            $this->recordAudit(
                $opportunity,
                'opportunity.item_removed',
                newValues: null,
                oldValues: $snapshot,
            );
        }
    }
}

<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\OpportunityItem;
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

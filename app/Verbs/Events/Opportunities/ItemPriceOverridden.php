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
 * Sets (or clears) a manual unit-price override on a line item. A non-null price
 * always wins over the resolved rate; null reverts the line to rate-engine
 * pricing. Repricing recomputes the line + parent totals; the availability demand
 * is unaffected by a price change.
 */
class ItemPriceOverridden extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public ?int $unit_price = null,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->manual_unit_price = $this->unit_price;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $oldUnitPrice = (int) $item->unit_price;

        $this->repriceAndRollUp($item, $state->manual_unit_price);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $item->refresh();

            $this->recordAudit(
                $opportunity,
                'opportunity.item_price_overridden',
                newValues: ['item_id' => $item->id, 'unit_price' => $item->unit_price, 'total' => $item->total],
                oldValues: ['item_id' => $item->id, 'unit_price' => $oldUnitPrice],
            );
        }
    }
}

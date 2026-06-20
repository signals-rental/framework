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
 * Changes a line item's quantity, repricing the line + parent totals and
 * resyncing the availability demand.
 */
class ItemQuantityChanged extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public string $quantity = '0',
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);

        // A reduction must not strand committed fulfilment: the new quantity may
        // not drop below the number of serialised assets already allocated to the
        // line, nor below the quantity already dispatched on a bulk line. Both are
        // read from the projection (validate() runs before handle(), so the rows
        // reflect the pre-mutation state) and replay-safely reproduce.
        $newQuantity = max(0, (int) round((float) $this->quantity));

        $allocatedAssets = OpportunityItemAsset::query()
            ->where('opportunity_item_id', $state->opportunity_item_id)
            ->count();

        $this->assert(
            $newQuantity >= $allocatedAssets,
            sprintf(
                'Cannot reduce the quantity below the %d already-allocated asset(s); deallocate first.',
                $allocatedAssets,
            ),
        );

        $dispatched = (int) round((float) $state->dispatched_quantity);

        $this->assert(
            $newQuantity >= $dispatched,
            sprintf(
                'Cannot reduce the quantity below the %d already-dispatched unit(s).',
                $dispatched,
            ),
        );
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->quantity = $this->quantity;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $oldQuantity = (string) $item->quantity;

        $item->forceFill(['quantity' => $state->quantity])->saveQuietly();

        $this->repriceAndRollUp($item, $state->manual_unit_price);
        $this->syncDemand($item);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $item->refresh();

            $this->recordAudit(
                $opportunity,
                'opportunity.item_quantity_changed',
                newValues: ['item_id' => $item->id, 'quantity' => (string) $item->quantity, 'total' => $item->total],
                oldValues: ['item_id' => $item->id, 'quantity' => $oldQuantity],
            );
        }
    }
}

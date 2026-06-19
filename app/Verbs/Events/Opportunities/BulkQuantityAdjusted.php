<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityItems;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Adjusts the requested quantity of a bulk line mid-cycle
 * (opportunity-lifecycle.md §5.5) — e.g. the client takes an extra 20m of cable, or
 * a counting error is corrected. Reprices the line + parent totals and resyncs the
 * demand at the new effective quantity.
 *
 * The new quantity cannot drop below what is already out on hire
 * (`dispatched_quantity`) — that stock is physically gone and is reconciled through
 * a return, not a downward adjustment.
 */
class BulkQuantityAdjusted extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public string $new_quantity = '0',
        public ?string $reason = null,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);

        $next = BigDecimal::of($this->new_quantity);

        $this->assert($next->isGreaterThanOrEqualTo(0), 'The adjusted quantity cannot be negative.');

        $this->assert(
            $next->isGreaterThanOrEqualTo(BigDecimal::of($state->dispatched_quantity)),
            'The adjusted quantity cannot drop below what is already dispatched.',
        );
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->quantity = BigDecimal::of($this->new_quantity)->toScale(2)->__toString();
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
        $this->syncDemand($item->refresh());

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $item->refresh();

            $this->recordAudit(
                $opportunity,
                'opportunity.bulk_quantity_adjusted',
                newValues: ['item_id' => $item->id, 'quantity' => (string) $item->quantity, 'reason' => $this->reason],
                oldValues: ['item_id' => $item->id, 'quantity' => $oldQuantity],
            );
        }
    }
}

<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityItems;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Records a (partial) return of a non-serialised bulk line
 * (opportunity-lifecycle.md §5.5 / §7.5). `returned_quantity` accrues but must
 * never exceed what is currently out on hire (`dispatched_quantity`).
 *
 * The effective demanded quantity drops by the returned amount
 * (`effective_quantity = quantity - returned_quantity`); the demand resync rewrites
 * the line's bulk demand at the reduced quantity (availability-engine.md "Partial
 * returns for bulk items"). Auto-promotion (§7.6) fires from {@see fired()}.
 */
class BulkQuantityReturned extends Event
{
    use PricesOpportunityItems, PromotesOpportunityStatus, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public string $quantity = '0',
        public ?int $received_by = null,
        public ?int $condition = null,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);

        $amount = BigDecimal::of($this->quantity);

        $this->assert($amount->isGreaterThan(0), 'The returned quantity must be positive.');

        $dispatched = BigDecimal::of($state->dispatched_quantity);
        $alreadyReturned = BigDecimal::of($state->returned_quantity);

        $this->assert(
            $alreadyReturned->plus($amount)->isLessThanOrEqualTo($dispatched),
            'Cannot return more than is currently out on hire.',
        );
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->returned_quantity = BigDecimal::of($state->returned_quantity)
            ->plus(BigDecimal::of($this->quantity))
            ->toScale(2)
            ->__toString();
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $item->forceFill(['returned_quantity' => $state->returned_quantity])->saveQuietly();
        $item->refresh();

        // Effective demand quantity = quantity - returned_quantity; resync rewrites
        // the bulk demand at the reduced quantity (and releases it entirely once
        // fully returned).
        $this->syncDemand($item);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.bulk_returned',
                newValues: [
                    'item_id' => $item->id,
                    'quantity' => $this->quantity,
                    'returned_quantity' => $state->returned_quantity,
                    'received_by' => $this->received_by,
                    'condition' => $this->condition,
                ],
                oldValues: null,
            );
        }
    }

    public function fired(OpportunityItemState $state): void
    {
        $this->promoteOpportunityFromItems(
            $this->opportunityForItem($state->opportunity_item_id),
            $this->singleBulkOverlay($state->opportunity_item_id, $state->dispatched_quantity, $state->returned_quantity),
        );
    }
}

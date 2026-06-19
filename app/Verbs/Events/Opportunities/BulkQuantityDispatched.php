<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState;
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
 * Records a (partial) dispatch of a non-serialised bulk line
 * (opportunity-lifecycle.md §5.5 / §7.5). Bulk products track no individual
 * assets, so the dispatch/return cycle is event-sourced as aggregate quantities on
 * the line. The parent opportunity must be an ORDER (mirrors the serialised
 * Order-only dispatch guard).
 *
 * Partial dispatch is first-class: 60m of a 100m cable line can go out now and 40m
 * later. `dispatched_quantity` accrues but must never exceed the requested
 * quantity. Auto-promotion (§7.6) fires from {@see fired()}.
 */
class BulkQuantityDispatched extends Event
{
    use PricesOpportunityItems, PromotesOpportunityStatus, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public string $quantity = '0',
        public ?int $dispatched_by = null,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);

        $amount = BigDecimal::of($this->quantity);

        $this->assert($amount->isGreaterThan(0), 'The dispatched quantity must be positive.');

        $already = BigDecimal::of($state->dispatched_quantity);
        $requested = BigDecimal::of($state->quantity);

        $this->assert(
            $already->plus($amount)->isLessThanOrEqualTo($requested),
            'Cannot dispatch more than the requested quantity.',
        );

        $opportunity = $this->opportunityFor($state);

        $this->assert(
            $opportunity !== null && $opportunity->statusEnum()->state() === OpportunityState::Order,
            'Bulk quantities can only be dispatched on an order.',
        );
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->dispatched_quantity = BigDecimal::of($state->dispatched_quantity)
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

        $item->forceFill(['dispatched_quantity' => $state->dispatched_quantity])->saveQuietly();
        $item->refresh();

        // Demand period follows the opportunity's now-Operational phase; the demand
        // resync picks it up after the fired() promotion commits its own event.
        $this->syncDemand($item);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.bulk_dispatched',
                newValues: [
                    'item_id' => $item->id,
                    'quantity' => $this->quantity,
                    'dispatched_quantity' => $state->dispatched_quantity,
                    'dispatched_by' => $this->dispatched_by,
                ],
                oldValues: null,
            );
        }
    }

    public function fired(OpportunityItemState $state): void
    {
        $this->promoteOpportunityFromItems(
            $this->opportunityFor($state),
            $this->singleBulkOverlay($state->opportunity_item_id, $state->dispatched_quantity, $state->returned_quantity),
        );
    }

    private function opportunityFor(OpportunityItemState $state): ?Opportunity
    {
        return OpportunityItem::query()->whereKey($state->opportunity_item_id)->first()?->opportunity()->first();
    }
}

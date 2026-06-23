<?php

namespace App\Verbs\Events\Opportunities\Concerns;

use App\Models\OpportunityItem;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\States\OpportunityItemState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

/**
 * Shared pricing + availability side-effects for opportunity line-item events.
 *
 * Centralises the two projection-time concerns every item event performs after
 * dual-writing its row:
 *
 *  - {@see repriceAndRollUp()} — reprice the line via the rate + tax engines and
 *    roll the totals up onto the parent. REPLAY-ACTIVE: it writes only to the
 *    opportunity/opportunity_items projection rows, idempotently, so replay
 *    reproduces identical totals with no external log churn.
 *
 *  - {@see syncDemand()} / {@see releaseDemand()} — keep the availability demand
 *    rows in step. SKIPPED ON REPLAY via {@see Verbs::unlessReplaying()}.
 *
 * REPLAY-SAFETY RATIONALE: demands + availability snapshots are a rebuildable
 * cache (a projection of truth). The DemandObserver recalculates snapshots and
 * writes availability_events SYNCHRONOUSLY on every demand mutation with NO
 * replay guard of its own. During a full Verbs::replay() we MUST rebuild the
 * opportunity_items projection (every event runs), but re-running syncDemands per
 * historical event would (a) churn the PG exclusion constraint via
 * delete+recreate, (b) write duplicate availability_events rows, and (c) trigger
 * O(events) redundant synchronous snapshot recalcs. The demand/availability
 * projection is rebuilt by its OWN dedicated path, not by replaying item events.
 * So the demand sync is replay-skipped, while the projection dual-write, the
 * totals recompute, and the audit bridge (which dedups on verb_event_id) all stay
 * replay-active.
 *
 * @mixin Event
 */
trait PricesOpportunityItems
{
    use RollsUpOpportunityTotals;

    /**
     * Guard a line-item mutation: the parent opportunity must not be closed and
     * the line must not already be removed. Reads the parent status from the
     * `opportunities` projection (validate() runs before handle(), so the
     * projection reflects the pre-mutation state).
     */
    protected function assertItemMutable(OpportunityItemState $state): void
    {
        $this->assert(
            ! $state->is_removed,
            'A removed line item cannot be modified.',
        );

        $this->assertOpportunityNotClosed($state->opportunity_id, 'line items');
    }

    /**
     * Reprice the item and roll the totals up onto the parent opportunity.
     */
    protected function repriceAndRollUp(OpportunityItem $item, ?int $manualUnitPrice = null): void
    {
        $calculator = app(OpportunityTotalsCalculator::class);
        $calculator->recalculateItem($item, $manualUnitPrice);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $calculator->rollUp($opportunity);
        }
    }

    /**
     * Sync the availability demand for the item — skipped on replay.
     */
    protected function syncDemand(OpportunityItem $item): void
    {
        Verbs::unlessReplaying(function () use ($item): void {
            app(OpportunityItemDemandResolver::class)->syncDemands($item);
        });
    }

    /**
     * Release (void) the availability demands for the item — skipped on replay.
     */
    protected function releaseDemand(OpportunityItem $item): void
    {
        Verbs::unlessReplaying(function () use ($item): void {
            app(OpportunityItemDemandResolver::class)->releaseDemands($item);
        });
    }

    /**
     * A compact snapshot of a line item for the audit trail.
     *
     * @return array<string, mixed>
     */
    protected function itemSnapshot(OpportunityItem $item): array
    {
        return [
            'item_id' => $item->id,
            'name' => $item->name,
            'product_id' => $item->itemable_id,
            'quantity' => (string) $item->quantity,
            'unit_price' => $item->unit_price,
            'total' => $item->total,
            'transaction_type' => (int) $item->getRawOriginal('transaction_type'),
            'is_optional' => $item->is_optional,
        ];
    }
}

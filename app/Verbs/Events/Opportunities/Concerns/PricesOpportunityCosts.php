<?php

namespace App\Verbs\Events\Opportunities\Concerns;

use App\Models\OpportunityCost;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\States\OpportunityCostState;
use Thunk\Verbs\Event;

/**
 * Shared tax + rollup side-effects for opportunity cost events.
 *
 * Mirrors {@see PricesOpportunityItems} for the cost projection. Costs are NOT
 * priced by the rate engine — they carry their own `amount` — so there is no
 * availability demand and no rate resolution; the only projection-time concern is
 * resolving the cost's tax rate and rolling the totals up onto the parent.
 *
 * REPLAY-ACTIVE: every method here writes only to the opportunity /
 * opportunity_costs projection rows, idempotently, so a full Verbs::replay()
 * reproduces identical totals with no external side-effects.
 *
 * @mixin Event
 */
trait PricesOpportunityCosts
{
    use RollsUpOpportunityTotals;

    /**
     * Guard a cost mutation: the parent opportunity must not be closed and the
     * cost must not already be removed. Reads the parent status from the
     * `opportunities` projection (validate() runs before handle(), so the
     * projection reflects the pre-mutation state).
     */
    protected function assertCostMutable(OpportunityCostState $state): void
    {
        $this->assert(
            ! $state->is_removed,
            'A removed cost cannot be modified.',
        );

        $this->assertOpportunityNotClosed($state->opportunity_id, 'costs');
    }

    /**
     * Resolve the cost's tax rate and roll the totals up onto the parent.
     */
    protected function repriceAndRollUp(OpportunityCost $cost): void
    {
        $calculator = app(OpportunityTotalsCalculator::class);
        $calculator->recalculateCost($cost);

        $opportunity = $cost->opportunity()->first();

        if ($opportunity !== null) {
            $calculator->rollUp($opportunity);
        }
    }

    /**
     * A compact snapshot of a cost for the audit trail.
     *
     * @return array<string, mixed>
     */
    protected function costSnapshot(OpportunityCost $cost): array
    {
        return [
            'cost_id' => $cost->id,
            'description' => $cost->description,
            'cost_type' => (int) $cost->getRawOriginal('cost_type'),
            'amount' => $cost->amount,
            'quantity' => (string) $cost->quantity,
            'transaction_type' => (int) $cost->getRawOriginal('transaction_type'),
            'is_optional' => $cost->is_optional,
        ];
    }
}

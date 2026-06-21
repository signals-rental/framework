<?php

namespace App\Verbs\Events\Opportunities\Concerns;

use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Thunk\Verbs\Event;

/**
 * Shared totals-rollup helper and closed-opportunity guard for the opportunity
 * line-item and cost pricing concerns ({@see PricesOpportunityItems},
 * {@see PricesOpportunityCosts}).
 *
 * Both pricing concerns roll the parent totals up identically and both guard a
 * mutation against a closed parent identically (differing only in the subject noun
 * surfaced in the assertion message). Centralising them keeps the two concerns to
 * their own distinct pricing logic.
 *
 * @mixin Event
 */
trait RollsUpOpportunityTotals
{
    /**
     * Roll the totals up onto the parent without repricing any line/cost (used after
     * a removal, where the row is gone, or an optional toggle that only shifts which
     * rows count toward the totals).
     */
    protected function rollUpOnly(Opportunity $opportunity): void
    {
        app(OpportunityTotalsCalculator::class)->rollUp($opportunity);
    }

    /**
     * Guard that the parent opportunity is not closed before a child mutation.
     * Reads the parent status from the `opportunities` projection (validate() runs
     * before handle(), so the projection reflects the pre-mutation state). The
     * `$subject` drives the message tail (e.g. 'line items', 'costs').
     */
    protected function assertOpportunityNotClosed(int $opportunityId, string $subject): void
    {
        $closed = Opportunity::query()
            ->whereKey($opportunityId)
            ->first()
            ?->statusEnum()
            ->isClosed() ?? false;

        $this->assert(
            ! $closed,
            "A closed opportunity's {$subject} cannot be modified.",
        );
    }
}

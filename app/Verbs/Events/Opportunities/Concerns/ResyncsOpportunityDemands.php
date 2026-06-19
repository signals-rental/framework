<?php

namespace App\Verbs\Events\Opportunities\Concerns;

use App\Models\Opportunity;
use App\Services\Availability\OpportunityItemDemandResolver;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

/**
 * Re-sync line-item availability demands after an opportunity status/state
 * transition.
 *
 * Item demands derive their phase from the parent opportunity status (the
 * ceiling principle), but they are only written by item events. A bare
 * state/status transition on the opportunity therefore leaves the demand rows
 * stale — an order's demands stay inactive (it never blocks stock); a Lost / Dead
 * / Cancelled / Complete deal's demands stay active (it keeps blocking stock).
 *
 * Transition events call {@see resyncOpportunityDemands()} from their handle(),
 * AFTER the projection update, so the resolver reads the post-transition status.
 *
 * REPLAY-SAFETY: like the demand sync in {@see PricesOpportunityItems}, this is
 * skipped on replay via {@see Verbs::unlessReplaying()}. Demands + availability
 * snapshots are a rebuildable projection with their own dedicated rebuild path;
 * re-running the resync per historical transition during a full replay would
 * churn the PG exclusion constraint and emit duplicate availability_events. The
 * resolver is container-resolved (mirroring the item events) so it remains
 * mockable in tests.
 *
 * @mixin Event
 */
trait ResyncsOpportunityDemands
{
    protected function resyncOpportunityDemands(Opportunity $opportunity): void
    {
        Verbs::unlessReplaying(function () use ($opportunity): void {
            app(OpportunityItemDemandResolver::class)->resyncForOpportunity($opportunity);
        });
    }
}

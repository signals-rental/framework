<?php

namespace App\Verbs\Events\Opportunities\Concerns;

use App\Models\OpportunityItem;
use App\Services\Availability\OpportunityItemDemandResolver;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

/**
 * Re-sync a line item's availability demands after a per-asset assignment event
 * changes which physical assets are allocated to it.
 *
 * The resolver ({@see OpportunityItemDemandResolver::syncDemands()}) already
 * derives the serialised demand split (one asset-specific demand per allocated
 * asset + a residual quantity-based demand for the unallocated remainder,
 * opportunity-lifecycle.md §9.3) from the line's current allocations. Asset events
 * therefore do not hand-roll demand-row math — they project the assignment change,
 * then call {@see syncAssetDemands()} so the resolver re-derives the whole demand
 * set from the new allocation picture.
 *
 * REPLAY-SAFETY: skipped on replay via {@see Verbs::unlessReplaying()}, mirroring
 * the item-event demand sync ({@see PricesOpportunityItems}).
 * Demands + availability snapshots are a rebuildable projection with their own
 * dedicated rebuild path; re-running the resync per historical asset event during a
 * full replay would churn the PG exclusion constraint and emit duplicate
 * availability_events.
 *
 * @mixin Event
 */
trait SyncsAssetDemands
{
    protected function syncAssetDemands(OpportunityItem $item): void
    {
        Verbs::unlessReplaying(function () use ($item): void {
            app(OpportunityItemDemandResolver::class)->syncDemands($item->refresh());
        });
    }
}

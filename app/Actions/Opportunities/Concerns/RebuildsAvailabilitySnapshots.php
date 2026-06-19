<?php

namespace App\Actions\Opportunities\Concerns;

use App\Jobs\DetectOverdueDemands;
use App\Jobs\RebuildSnapshotsJob;
use App\Jobs\RecalculateAvailabilityJob;
use App\Models\OpportunityItem;
use App\Observers\DemandObserver;

/**
 * Wires the on-demand snapshot maintenance rebuild into the dispatch/return flow.
 *
 * Day-to-day availability is already kept current by the debounced
 * {@see RecalculateAvailabilityJob} the {@see DemandObserver}
 * enqueues on every demand write — and a dispatch/return resync IS a demand write,
 * so the rolling-horizon recompute is covered automatically. We deliberately do NOT
 * re-dispatch that job here (no double-dispatch).
 *
 * What the debounced recompute does NOT fully cover is a return that contracts a
 * demand window which had been extended far into the future — most notably an
 * overdue demand that {@see DetectOverdueDemands} pushed out to the
 * sentinel date. Returning it shrinks the unavailable window across a long horizon,
 * and the surest way to re-materialise the now-freed far slots is a full
 * {@see RebuildSnapshotsJob} over the rolling horizon (it clamps + upserts
 * idempotently). It is dispatched once per distinct product/store touched by the
 * batch, from the action (request-time) — never from an event handle(), so it never
 * runs during a Verbs replay.
 */
trait RebuildsAvailabilitySnapshots
{
    /**
     * Dispatch a full snapshot rebuild for each distinct product/store the given
     * line items resolve to. De-duplicated so a batch touching many assets of the
     * same line enqueues a single rebuild per product/store.
     *
     * @param  iterable<OpportunityItem>  $items
     */
    protected function rebuildSnapshotsForItems(iterable $items): void
    {
        $seen = [];

        foreach ($items as $item) {
            $productId = $item->item_id;
            $opportunity = $item->relationLoaded('opportunity') ? $item->opportunity : $item->opportunity()->first();
            $storeId = $item->dispatch_store_id ?? $opportunity?->store_id;

            if ($productId === null || $storeId === null) {
                continue;
            }

            $key = $productId.':'.$storeId;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            RebuildSnapshotsJob::dispatch((int) $productId, (int) $storeId);
        }
    }
}

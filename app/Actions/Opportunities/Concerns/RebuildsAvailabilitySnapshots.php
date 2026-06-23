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
 *
 * **Sync vs async (availability.async_threshold_products).** A small batch
 * touching at most `availability.async_threshold_products` distinct products is
 * rebuilt INLINE (`dispatchSync`) so the caller sees a fresh read model
 * immediately. A larger batch — which would block the request on many
 * rolling-horizon recomputes — is QUEUED instead, one job per product/store, so
 * the request returns promptly and the workers absorb the recompute load.
 */
trait RebuildsAvailabilitySnapshots
{
    /**
     * Dispatch a full snapshot rebuild for each distinct product/store the given
     * line items resolve to. De-duplicated so a batch touching many assets of the
     * same line enqueues a single rebuild per product/store.
     *
     * When the number of distinct PRODUCTS touched is at or below
     * `availability.async_threshold_products` the rebuilds run inline (synchronous)
     * for immediate consistency; above the threshold they are queued so a large
     * batch never blocks the request.
     *
     * @param  iterable<OpportunityItem>  $items
     */
    protected function rebuildSnapshotsForItems(iterable $items): void
    {
        $seen = [];

        foreach ($items as $item) {
            $productId = $item->itemable_id;
            $opportunity = $item->relationLoaded('opportunity') ? $item->opportunity : $item->opportunity()->first();
            $storeId = $item->dispatch_store_id ?? $opportunity?->store_id;

            if ($productId === null || $storeId === null) {
                continue;
            }

            $key = (int) $productId.':'.(int) $storeId;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = ['product_id' => (int) $productId, 'store_id' => (int) $storeId];
        }

        if ($seen === []) {
            return;
        }

        $distinctProducts = count(array_unique(array_column($seen, 'product_id')));
        $threshold = (int) settings('availability.async_threshold_products', 10);
        $runInline = $distinctProducts <= $threshold;

        foreach ($seen as $pair) {
            if ($runInline) {
                RebuildSnapshotsJob::dispatchSync($pair['product_id'], $pair['store_id']);
            } else {
                RebuildSnapshotsJob::dispatch($pair['product_id'], $pair['store_id']);
            }
        }
    }
}

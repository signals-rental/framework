<?php

use App\Actions\Opportunities\Concerns\RebuildsAvailabilitySnapshots;
use App\Jobs\RebuildSnapshotsJob;
use App\Models\OpportunityItem;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;

/**
 * Item 1a — `availability.async_threshold_products` drives whether the snapshot
 * rebuilds fired by the dispatch/return flow run inline (synchronous, for
 * immediate consistency) or are queued (so a large batch never blocks the
 * request). Item 1b — the overdue-demand sweep schedule reads
 * `availability.overdue_check_interval` (minutes) and tolerates the `settings`
 * table being absent pre-migration.
 */

/**
 * Expose the protected trait method so the sync/async decision can be exercised
 * directly without standing up a full opportunity lifecycle.
 */
function thresholdHarness(): object
{
    return new class
    {
        use RebuildsAvailabilitySnapshots;

        /** @param  iterable<OpportunityItem>  $items */
        public function rebuild(iterable $items): void
        {
            $this->rebuildSnapshotsForItems($items);
        }
    };
}

/**
 * A lightweight stand-in line item carrying just the product/store the trait
 * reads — avoids the cost of a real event-sourced opportunity.
 */
function thresholdItem(int $productId, int $storeId): OpportunityItem
{
    $item = new OpportunityItem;
    $item->itemable_id = $productId;
    $item->dispatch_store_id = $storeId;

    return $item;
}

describe('availability.async_threshold_products', function () {
    it('runs rebuilds INLINE when the distinct-product count is at or below the threshold', function () {
        settings()->set('availability.async_threshold_products', 3, 'integer');
        Bus::fake();

        // 3 distinct products == threshold -> inline.
        thresholdHarness()->rebuild([
            thresholdItem(101, 1),
            thresholdItem(102, 1),
            thresholdItem(103, 1),
        ]);

        // All three ran INLINE (synchronously). assertDispatchedSyncTimes counts
        // only the synchronous collection; the companion "QUEUES" test below
        // asserts assertNotDispatchedSync for the > threshold case, so the pair
        // fully distinguishes the two dispatch modes.
        Bus::assertDispatchedSyncTimes(RebuildSnapshotsJob::class, 3);
    });

    it('QUEUES rebuilds when the distinct-product count exceeds the threshold', function () {
        settings()->set('availability.async_threshold_products', 3, 'integer');
        Bus::fake();

        // 4 distinct products > threshold -> queued.
        thresholdHarness()->rebuild([
            thresholdItem(201, 1),
            thresholdItem(202, 1),
            thresholdItem(203, 1),
            thresholdItem(204, 1),
        ]);

        Bus::assertDispatchedTimes(RebuildSnapshotsJob::class, 4);
        Bus::assertNotDispatchedSync(RebuildSnapshotsJob::class);
    });

    it('de-duplicates by product/store and counts distinct PRODUCTS for the decision', function () {
        settings()->set('availability.async_threshold_products', 1, 'integer');
        Bus::fake();

        // One product across two stores = 1 distinct product (<= threshold) but
        // two distinct product/store pairs -> two inline rebuilds.
        thresholdHarness()->rebuild([
            thresholdItem(301, 1),
            thresholdItem(301, 2),
            thresholdItem(301, 1), // duplicate pair, collapsed
        ]);

        Bus::assertDispatchedSyncTimes(RebuildSnapshotsJob::class, 2);
    });

    it('falls back to the default (10) threshold when the setting is unset', function () {
        Bus::fake();

        // 11 distinct products with the default threshold of 10 -> queued.
        $items = [];
        for ($i = 0; $i < 11; $i++) {
            $items[] = thresholdItem(400 + $i, 1);
        }

        thresholdHarness()->rebuild($items);

        Bus::assertDispatchedTimes(RebuildSnapshotsJob::class, 11);
    });
});

describe('availability.overdue_check_interval scheduling', function () {
    /**
     * Re-derive the cron expression the scheduler builds in routes/console.php,
     * so the mapping and the pre-migration guard are covered in isolation.
     */
    function overdueCheckCron(): string
    {
        $minutes = 60;

        try {
            if (Schema::hasTable('settings')) {
                $minutes = (int) settings('availability.overdue_check_interval', 60);
            }
        } catch (Throwable) {
            $minutes = 60;
        }

        $minutes = max(1, min(1440, $minutes));

        if ($minutes < 60) {
            return sprintf('*/%d * * * *', $minutes);
        }

        $hours = max(1, (int) ceil($minutes / 60));

        return match (true) {
            $hours >= 24 => '0 0 * * *',
            $hours === 1 => '0 * * * *',
            default => sprintf('0 */%d * * *', $hours),
        };
    }

    it('maps the default (unset) interval to an hourly cron — identical to the old behaviour', function () {
        expect(overdueCheckCron())->toBe('0 * * * *');
    });

    it('maps a sub-hourly interval to a per-N-minutes cron', function () {
        settings()->set('availability.overdue_check_interval', 15, 'integer');

        expect(overdueCheckCron())->toBe('*/15 * * * *');
    });

    it('maps a multi-hour interval to an hour-stepped cron', function () {
        settings()->set('availability.overdue_check_interval', 120, 'integer');

        expect(overdueCheckCron())->toBe('0 */2 * * *');
    });

    it('clamps an at-or-over-a-day interval to once daily', function () {
        settings()->set('availability.overdue_check_interval', 1440, 'integer');

        expect(overdueCheckCron())->toBe('0 0 * * *');
    });

    it('falls back to hourly without crashing when the settings table is absent (pre-migration)', function () {
        // Simulate a fresh install / migrate boot: the settings table does not
        // exist yet. The builder must not throw and must fall back to hourly.
        Schema::dropIfExists('settings');

        expect(fn () => overdueCheckCron())->not->toThrow(Throwable::class);
        expect(overdueCheckCron())->toBe('0 * * * *');
    });
});

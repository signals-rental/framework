<?php

namespace App\Observers;

use App\Enums\AvailabilityEventType;
use App\Jobs\RecalculateAvailabilityJob;
use App\Models\AvailabilityEvent;
use App\Models\Product;
use App\Models\StockLevel;
use App\Services\Availability\RecalculationPipeline;

/**
 * Keeps availability snapshots consistent when on-hand stock changes.
 *
 * Stock is plain Eloquent (not event-sourced), so there is no replay concern: a
 * create/update/delete that changes the sellable on-hand quantity for a
 * product/store enqueues a {@see RecalculateAvailabilityJob} for that
 * product/store and appends a `stock_changed` availability event.
 *
 * This closes the M2-review range-read gap two ways:
 *
 *  1. A stocked-but-un-demanded product now gets snapshots — previously
 *     `getAvailabilityRange()` returned empty for it because only demand changes
 *     triggered the pipeline.
 *  2. Adjusting stock (write-off, count, purchase) re-derives `available` so the
 *     snapshot read model tracks the new on-hand quantity.
 *
 * The trigger is **asynchronous/debounced** in M3-4: the job — not the observer —
 * runs the {@see RecalculationPipeline} over the
 * rolling horizon, on a Horizon-managed queue, coalescing a burst of stock
 * writes for the same product/store into a single recompute. A
 * `quantity_held`-only change still enqueues, because the totals shift.
 *
 * **Recalc-storm guard:** bulk operations (seeders, imports) that write many
 * stock rows can set `availability.suppress_stock_recalc` to skip enqueuing the
 * per-row recompute and run a single bounded recalculation afterwards. The flag
 * stays meaningful even with a `sync` queue (the suite default): under `sync` a
 * dispatched job runs inline, so suppressing dispatch is what prevents the storm.
 *
 * The job writes only snapshots, daily summaries and availability events — never
 * stock levels — so there is no observer recursion.
 */
class StockLevelObserver
{
    /**
     * Fields whose change can alter the sellable on-hand quantity for a
     * product/store, and therefore the availability snapshots derived from it.
     *
     * @var list<string>
     */
    private const array QUANTITY_AFFECTING_FIELDS = [
        'quantity_held',
        'stock_category',
        'product_id',
        'store_id',
        'starts_at',
        'ends_at',
    ];

    public function created(StockLevel $stockLevel): void
    {
        $this->dispatchRecalculation((int) $stockLevel->product_id, (int) $stockLevel->store_id);
        $this->log($stockLevel, 'created');
    }

    public function updated(StockLevel $stockLevel): void
    {
        if (! $this->touchesQuantity($stockLevel)) {
            return;
        }

        // A move between products/stores must refresh both the source and the
        // destination, since stock left one basis and joined another.
        $originalProduct = (int) ($stockLevel->getOriginal('product_id') ?? $stockLevel->product_id);
        $originalStore = (int) ($stockLevel->getOriginal('store_id') ?? $stockLevel->store_id);

        $this->dispatchRecalculation($originalProduct, $originalStore);

        if ($originalProduct !== (int) $stockLevel->product_id || $originalStore !== (int) $stockLevel->store_id) {
            $this->dispatchRecalculation((int) $stockLevel->product_id, (int) $stockLevel->store_id);
        }

        $this->log($stockLevel, 'updated');
    }

    public function deleted(StockLevel $stockLevel): void
    {
        $this->dispatchRecalculation((int) $stockLevel->product_id, (int) $stockLevel->store_id);
        $this->log($stockLevel, 'deleted');
    }

    /**
     * Whether the update changed any field that affects on-hand quantity.
     */
    private function touchesQuantity(StockLevel $stockLevel): bool
    {
        return $stockLevel->wasChanged(self::QUANTITY_AFFECTING_FIELDS);
    }

    /**
     * Enqueue a debounced availability recompute for the product/store. The job
     * recomputes the full rolling horizon and no-ops for products that do not
     * track availability, so the only guards needed here are the bulk-seed
     * suppression flag and a cheap "is this product tracked?" pre-check that
     * avoids enqueuing obviously pointless work.
     */
    private function dispatchRecalculation(int $productId, int $storeId): void
    {
        if ($this->suppressed()) {
            return;
        }

        if (! Product::query()->whereKey($productId)->where('track_availability', true)->exists()) {
            return;
        }

        RecalculateAvailabilityJob::dispatch($productId, $storeId);
    }

    /**
     * Whether stock-change recalculation is suppressed (bulk-seed guard).
     */
    private function suppressed(): bool
    {
        return (bool) config('availability.suppress_stock_recalc', false);
    }

    /**
     * Append a `stock_changed` availability event describing the adjustment.
     * Always logged (even when suppressed/un-tracked) so the audit trail records
     * the stock movement regardless of whether a recompute was enqueued.
     */
    private function log(StockLevel $stockLevel, string $change): void
    {
        AvailabilityEvent::query()->create([
            'event_type' => AvailabilityEventType::StockChanged,
            'product_id' => $stockLevel->product_id,
            'store_id' => $stockLevel->store_id,
            'demand_id' => null,
            'source_type' => null,
            'source_id' => null,
            'payload' => [
                'change' => $change,
                'stock_level_id' => $stockLevel->id,
                'stock_category' => $stockLevel->getAttributeValue('stock_category')?->value,
                'quantity_held' => (float) $stockLevel->quantity_held,
            ],
        ]);
    }
}

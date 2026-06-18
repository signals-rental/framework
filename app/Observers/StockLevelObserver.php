<?php

namespace App\Observers;

use App\Enums\AvailabilityEventType;
use App\Models\AvailabilityEvent;
use App\Models\Product;
use App\Models\StockLevel;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;

/**
 * Keeps availability snapshots consistent when on-hand stock changes.
 *
 * Stock is plain Eloquent (not event-sourced), so there is no replay concern: a
 * create/update/delete that changes the sellable on-hand quantity for a
 * product/store immediately recalculates that product/store over the rolling
 * snapshot horizon and appends a `stock_changed` availability event.
 *
 * This closes the M2-review range-read gap two ways:
 *
 *  1. A stocked-but-un-demanded product now gets snapshots — previously
 *     `getAvailabilityRange()` returned empty for it because only demand changes
 *     triggered the pipeline.
 *  2. Adjusting stock (write-off, count, purchase) re-derives `available` so the
 *     snapshot read model tracks the new on-hand quantity.
 *
 * The trigger is **synchronous** in M3-3 (debounced/queued dispatch is M3-4) and
 * scoped to the affected product/store only — never a full rebuild. A
 * `quantity_held`-only change still recalculates, because the totals shift.
 *
 * **Recalc-storm guard:** bulk operations (seeders, imports) that write many
 * stock rows can set `availability.suppress_stock_recalc` to skip the per-row
 * recalc and run a single bounded recalculation afterwards.
 *
 * The pipeline writes only snapshots, daily summaries and availability events —
 * never stock levels — so there is no observer recursion.
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

    public function __construct(
        private readonly RecalculationPipeline $pipeline,
    ) {}

    public function created(StockLevel $stockLevel): void
    {
        $this->recalculate((int) $stockLevel->product_id, (int) $stockLevel->store_id);
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

        $this->recalculate($originalProduct, $originalStore);

        if ($originalProduct !== (int) $stockLevel->product_id || $originalStore !== (int) $stockLevel->store_id) {
            $this->recalculate((int) $stockLevel->product_id, (int) $stockLevel->store_id);
        }

        $this->log($stockLevel, 'updated');
    }

    public function deleted(StockLevel $stockLevel): void
    {
        $this->recalculate((int) $stockLevel->product_id, (int) $stockLevel->store_id);
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
     * Recalculate the product/store over the full rolling snapshot horizon so
     * the new on-hand quantity is reflected across the whole materialised window.
     * The pipeline itself clamps the window to the configured horizon and no-ops
     * for products that do not track availability.
     */
    private function recalculate(int $productId, int $storeId): void
    {
        if ($this->suppressed()) {
            return;
        }

        if (! Product::query()->whereKey($productId)->where('track_availability', true)->exists()) {
            return;
        }

        $now = Carbon::now('UTC');
        $pastDays = (int) config('availability.snapshot_horizon.past_days', 90);
        $futureDays = (int) config('availability.snapshot_horizon.future_days', 365);

        $from = $now->copy()->subDays(max(0, $pastDays))->startOfDay();
        $to = $now->copy()->addDays(max(0, $futureDays))->endOfDay();

        $this->pipeline->recalculate($productId, $storeId, $from, $to);
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
     * the stock movement regardless of whether snapshots were refreshed.
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

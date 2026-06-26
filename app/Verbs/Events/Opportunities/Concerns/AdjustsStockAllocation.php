<?php

namespace App\Verbs\Events\Opportunities\Concerns;

use App\Models\OpportunityItem;
use App\Models\StockLevel;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\AvailabilityService;
use Brick\Math\BigDecimal;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

/**
 * Keeps `stock_levels.quantity_allocated` in step with per-asset assignment
 * events.
 *
 * `quantity_allocated` is a projection of truth (how many units of a stock level
 * are committed to opportunities), mirroring how the shortage resolver writes it.
 * It is therefore adjusted on EVERY run — including replay — so a truncate +
 * Verbs::replay() rebuild reproduces the same allocated counts. (Contrast with the
 * availability DEMAND rows, which are a rebuildable cache with their own replay
 * path and so are skipped on replay; see {@see SyncsAssetDemands}.)
 *
 * The decimal arithmetic uses {@see BigDecimal} to stay exact on the
 * `decimal(…, 2)` column and is clamped at zero so a decrement can never drive the
 * allocated count negative.
 *
 * @mixin Event
 */
trait AdjustsStockAllocation
{
    /**
     * Increment a stock level's allocated quantity by the given (positive) amount.
     */
    protected function incrementStockAllocation(?int $stockLevelId, string $by): void
    {
        $this->adjustStockAllocation($stockLevelId, $by, increment: true);
    }

    /**
     * Decrement a stock level's allocated quantity by the given (positive) amount,
     * clamped at zero.
     */
    protected function decrementStockAllocation(?int $stockLevelId, string $by): void
    {
        $this->adjustStockAllocation($stockLevelId, $by, increment: false);
    }

    private function adjustStockAllocation(?int $stockLevelId, string $by, bool $increment): void
    {
        if ($stockLevelId === null) {
            return;
        }

        // Pessimistically lock the row for the duration of this read-modify-write
        // so two concurrent allocations against the same stock level can never both
        // read the same baseline and clobber each other's increment. These events
        // always run inside the caller's commitVerbs → DB::transaction boundary, so
        // the lock is held until that transaction commits.
        $stockLevel = StockLevel::query()->whereKey($stockLevelId)->lockForUpdate()->first();

        if ($stockLevel === null) {
            return;
        }

        $current = BigDecimal::of((string) ($stockLevel->quantity_allocated ?? '0'));
        $delta = BigDecimal::of($by);

        $next = $increment
            ? $current->plus($delta)
            : $current->minus($delta);

        // Clamp at zero — a decrement must never drive the allocated count negative.
        if ($next->isLessThan(0)) {
            $next = BigDecimal::zero();
        }

        $stockLevel->forceFill(['quantity_allocated' => $next->toScale(2)->__toString()])->saveQuietly();
    }

    /**
     * Over-allocation guard: assert the given serialised asset is free for the
     * line item's effective window.
     *
     * `checkAssetAvailable()` excludes an asset only when an ACTIVE demand claims
     * that specific `asset_id` over the window. The allocating line's OWN existing
     * demand is a quantity-based (asset_id null) row, so it never matches this
     * asset-specific check — the line cannot block itself here, while any OTHER
     * opportunity holding this physical asset correctly does. The asset-specific
     * demand for THIS allocation is written only after the guard passes.
     *
     * Skipped during replay: the booking is historical truth (the original
     * allocation already passed this gate) and the availability read model is
     * rebuilt by its own dedicated path.
     */
    protected function assertAssetAvailableForItem(OpportunityItem $item, int $stockLevelId): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        $context = app(OpportunityItemDemandResolver::class)->resolveContext($item);

        $available = app(AvailabilityService::class)->checkAssetAvailable(
            $stockLevelId,
            $context['from'],
            $context['to'],
        );

        $this->assert($available, 'The selected asset is not available for the line item\'s period.');
    }
}

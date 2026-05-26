<?php

namespace App\Services\RateEngine;

use App\Enums\RateTransactionType;
use App\Models\ProductRate;
use Illuminate\Support\Collection;

/**
 * Detects product rate assignments that share a product, store, transaction type
 * and priority with overlapping validity windows. Such overlaps are not blocked
 * (priority is meant to resolve them) but usually indicate a configuration error
 * worth warning the user about.
 */
class ProductRateOverlapChecker
{
    /**
     * Existing rates that overlap the given assignment at the same priority.
     *
     * @return Collection<int, ProductRate>
     */
    public function overlapping(
        int $productId,
        ?int $storeId,
        RateTransactionType $transactionType,
        int $priority,
        ?string $validFrom,
        ?string $validTo,
        ?int $exceptId = null,
    ): Collection {
        return ProductRate::query()
            ->where('product_id', $productId)
            ->where('transaction_type', $transactionType)
            ->where('priority', $priority)
            ->where(function ($query) use ($storeId): void {
                $storeId === null ? $query->whereNull('store_id') : $query->where('store_id', $storeId);
            })
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->get()
            ->filter(fn (ProductRate $rate): bool => $this->rangesOverlap(
                $validFrom,
                $validTo,
                $rate->valid_from?->toDateString(),
                $rate->valid_to?->toDateString(),
            ))
            ->values();
    }

    /**
     * Whether two date ranges overlap, treating a null bound as open-ended
     * (null from = -infinity, null to = +infinity).
     */
    private function rangesOverlap(?string $aFrom, ?string $aTo, ?string $bFrom, ?string $bTo): bool
    {
        return ($aFrom === null || $bTo === null || $aFrom <= $bTo)
            && ($bFrom === null || $aTo === null || $bFrom <= $aTo);
    }
}

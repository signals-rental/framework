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
        $query = ProductRate::query()
            ->where('product_id', $productId)
            ->where('transaction_type', $transactionType)
            ->where('priority', $priority)
            ->where(function ($query) use ($storeId): void {
                $storeId === null ? $query->whereNull('store_id') : $query->where('store_id', $storeId);
            })
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId));

        // Two windows overlap unless one ends before the other starts. A null
        // bound is open-ended (null from = -infinity, null to = +infinity), so a
        // null on either side of a comparison means that edge can't exclude a row.
        if ($validFrom !== null) {
            $query->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $validFrom));
        }

        if ($validTo !== null) {
            $query->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $validTo));
        }

        return $query->get();
    }
}

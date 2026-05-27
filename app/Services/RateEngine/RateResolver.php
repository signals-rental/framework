<?php

namespace App\Services\RateEngine;

use App\Enums\RateTransactionType;
use App\Models\Product;
use App\Models\ProductRate;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves which {@see ProductRate} applies to a product for a given store,
 * transaction type and date. Matching rates are ranked by priority (highest
 * first), with store-specific rates preferred over all-stores rates at equal
 * priority. Returns null when nothing matches, in which case the caller falls
 * back to the product's default pricing (unit price × duration, no engine logic).
 *
 * Results are cached per product. Writing a product rate invalidates that
 * product's cache; writing any rate definition invalidates all resolution
 * caches (a cached resolution carries its loaded definition, so a definition
 * edit must never serve stale config). Both invalidations are wired through the
 * models' lifecycle events.
 */
class RateResolver
{
    /**
     * Tag applied to every resolution cache entry; flushed when any rate
     * definition is written.
     */
    public const RESOLUTION_TAG = 'rate-resolution';

    public function resolve(
        Product $product,
        RateTransactionType $transactionType,
        ?int $storeId = null,
        ?CarbonInterface $date = null,
    ): ?ProductRate {
        $date ??= Carbon::now();

        if (! Cache::supportsTags()) {
            return $this->query($product, $transactionType, $storeId, $date);
        }

        $key = sprintf(
            'rate-resolve:%d:%s:%s:%s',
            $product->id,
            $storeId ?? 'all',
            $transactionType->value,
            $date->toDateString(),
        );

        // Cap the TTL (max 1 hour) so an entry resolved for "today" cannot
        // outlive the day it was computed for. The date is part of the key, so a
        // new day already produces a fresh entry; this is belt-and-braces.
        $ttl = max(60, min(3600, (int) Carbon::now()->diffInSeconds($date->copy()->endOfDay(), false)));

        return Cache::tags([self::RESOLUTION_TAG, self::productTag($product->id)])
            ->remember($key, $ttl, fn (): ?ProductRate => $this->query($product, $transactionType, $storeId, $date));
    }

    /**
     * Highest-priority product rate for the given criteria, or null.
     */
    private function query(
        Product $product,
        RateTransactionType $transactionType,
        ?int $storeId,
        CarbonInterface $date,
    ): ?ProductRate {
        // valid_from/valid_to are date columns (no time); compare on the calendar
        // date so a rate stays valid for the whole of its final day.
        $onDate = $date->toDateString();

        return ProductRate::query()
            ->with('rateDefinition')
            ->where('product_id', $product->id)
            ->where('transaction_type', $transactionType)
            ->where(function ($query) use ($storeId): void {
                $query->whereNull('store_id');

                if ($storeId !== null) {
                    $query->orWhere('store_id', $storeId);
                }
            })
            ->where(function ($query) use ($onDate): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $onDate);
            })
            ->where(function ($query) use ($onDate): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $onDate);
            })
            ->orderByDesc('priority')
            ->orderByRaw('store_id IS NULL') // store-specific (non-null) first
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Flush every cached resolution for a single product.
     */
    public static function flushProduct(int $productId): void
    {
        if (Cache::supportsTags()) {
            Cache::tags([self::productTag($productId)])->flush();
        }
    }

    /**
     * Flush every cached resolution (used when a rate definition changes).
     */
    public static function flushAll(): void
    {
        if (Cache::supportsTags()) {
            Cache::tags([self::RESOLUTION_TAG])->flush();
        }
    }

    private static function productTag(int $productId): string
    {
        return 'rate-product:'.$productId;
    }
}

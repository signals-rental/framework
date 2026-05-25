<?php

namespace App\ValueObjects;

use App\Enums\BasePeriod;
use App\Enums\RateTransactionType;
use Illuminate\Support\Carbon;

/**
 * Immutable input to the rate calculation pipeline.
 *
 * Carries everything a calculation strategy or modifier needs to derive a
 * charge: the unit price (in minor units), the rental window, quantity, the
 * resolved base period, the strategy's validated config, and contextual
 * metadata (store, transaction type, free-form extras).
 */
class CalculationContext
{
    /**
     * @param  int  $unitPriceMinor  Per-unit price in currency minor units (pence, cents, fils)
     * @param  string  $currency  ISO 4217 currency code
     * @param  Carbon  $start  Rental window start (UTC)
     * @param  Carbon  $end  Rental window end (UTC)
     * @param  int  $quantity  Number of units being charged
     * @param  BasePeriod|null  $basePeriod  Resolved base period, or null for fixed strategies
     * @param  array<string, mixed>  $strategyConfig  Validated strategy configuration
     * @param  RateTransactionType  $transactionType  Transaction context (rental, sale, service)
     * @param  int|null  $storeId  Store the charge applies at, or null for all stores
     * @param  int|null  $usageUnits  Actual usage units when a usage-based strategy is in play
     * @param  array<string, mixed>  $extra  Free-form metadata for plugin strategies/modifiers
     */
    public function __construct(
        public readonly int $unitPriceMinor,
        public readonly string $currency,
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly int $quantity,
        public readonly ?BasePeriod $basePeriod,
        public readonly array $strategyConfig,
        public readonly RateTransactionType $transactionType,
        public readonly ?int $storeId = null,
        public readonly ?int $usageUnits = null,
        public readonly array $extra = [],
    ) {}
}

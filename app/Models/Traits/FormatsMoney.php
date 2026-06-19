<?php

namespace App\Models\Traits;

use App\Support\Formatter;
use Brick\Money\Currency;
use Brick\Money\Money;

/**
 * Formats integer minor-unit money columns into decimal strings for API
 * responses, respecting the owning record's currency and that currency's
 * natural minor-unit scale (JPY 0dp, GBP/USD/EUR 2dp, KWD 3dp).
 *
 * Each consuming model resolves its own currency by overriding
 * {@see moneyFormattingCurrency()}; the default falls back to the company base
 * currency setting (never a hardcoded 'GBP'). Currency-aware DISPLAY formatting
 * (symbols, separators) lives in {@see Formatter}; this trait only
 * produces the raw RMS-compatible decimal string.
 */
trait FormatsMoney
{
    /**
     * Format a money value from minor units to a decimal string for API responses.
     *
     * Uses brick/money minor-unit conversion (not float division) so large integer
     * amounts never accrue binary-float drift, and renders at the resolved
     * currency's natural fraction-digit scale so non-2dp currencies (JPY 0dp,
     * KWD 3dp) are neither padded nor truncated.
     */
    public function formatMoneyCost(string $attribute): string
    {
        $value = (int) $this->getAttribute($attribute);
        $currency = Currency::of($this->moneyFormattingCurrency());

        return (string) Money::ofMinor($value, $currency)
            ->getAmount()
            ->toScale($currency->getDefaultFractionDigits());
    }

    /**
     * Resolve the ISO 4217 currency code used to format this record's money
     * columns. Models that carry their own currency (or can reach it via a parent)
     * override this; otherwise the company base-currency setting is used.
     *
     * Overrides cannot call `parent::` (this lives on a trait, not a parent class),
     * so they delegate their final fallback to {@see baseFormattingCurrency()}.
     */
    protected function moneyFormattingCurrency(): string
    {
        return $this->baseFormattingCurrency();
    }

    /**
     * The company base-currency setting, used as the universal fallback when a
     * record has no currency of its own (never a hardcoded 'GBP' literal — the
     * 'GBP' here is only the setting's own default when entirely unconfigured).
     */
    final protected function baseFormattingCurrency(): string
    {
        $base = settings('company.base_currency', 'GBP');

        return is_string($base) && $base !== '' ? $base : 'GBP';
    }
}

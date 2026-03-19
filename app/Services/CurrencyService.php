<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Brick\Math\RoundingMode;
use Brick\Money\Context\CustomContext;
use Brick\Money\Currency as BrickCurrency;
use Brick\Money\Money;
use Illuminate\Support\Carbon;

/**
 * Handles currency conversion using exchange rates with lossless arithmetic.
 *
 * Uses RationalMoney for intermediate calculations and rounds only at the
 * final step to preserve precision.
 */
class CurrencyService
{
    /**
     * Convert an amount in minor units from one currency to another.
     *
     * Uses RationalMoney for lossless intermediate arithmetic, rounding
     * only at the final step.
     */
    public function convert(int $amount, string $from, string $to, ?Carbon $date = null): int
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = $this->getRate($from, $to, $date);
        $sourceMoney = Money::ofMinor($amount, $from);
        $rational = $sourceMoney->toRational()->multipliedBy($rate);

        $targetCurrency = BrickCurrency::of($to);
        $context = new CustomContext($targetCurrency->getDefaultFractionDigits());

        return $rational->to($context, RoundingMode::HALF_UP)->getMinorAmount()->toInt();
    }

    /**
     * Get the exchange rate between two currencies as a string for precision.
     *
     * @param  bool  $allowTriangulation  Whether to attempt triangulation through base currency
     */
    public function getRate(string $from, string $to, ?Carbon $date = null, bool $allowTriangulation = true): string
    {
        if ($from === $to) {
            return '1.00000000';
        }

        $date = $date ?? Carbon::now();

        $exchangeRate = ExchangeRate::query()
            ->forPair($from, $to)
            ->effectiveAt($date)
            ->orderByDesc('effective_at')
            ->first();

        if ($exchangeRate) {
            return (string) $exchangeRate->rate;
        }

        // Try inverse lookup
        $inverse = ExchangeRate::query()
            ->forPair($to, $from)
            ->effectiveAt($date)
            ->orderByDesc('effective_at')
            ->first();

        if ($inverse) {
            return (string) $inverse->inverse_rate;
        }

        // Triangulate through base currency (non-recursive)
        if ($allowTriangulation) {
            $baseCode = settings('company.base_currency');
            if ($baseCode && $from !== $baseCode && $to !== $baseCode) {
                try {
                    $fromToBase = $this->getRate($from, $baseCode, $date, false);
                    $baseToTarget = $this->getRate($baseCode, $to, $date, false);

                    return bcmul($fromToBase, $baseToTarget, 8);
                } catch (\RuntimeException $e) {
                    report($e);
                }
            }
        }

        throw new \RuntimeException("No exchange rate found for {$from} to {$to}");
    }

    /**
     * Get the base currency from settings.
     */
    public function baseCurrency(): Currency
    {
        $code = settings('company.base_currency');

        return Currency::query()->where('code', $code)->firstOrFail();
    }
}

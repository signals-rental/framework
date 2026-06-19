<?php

namespace App\Models\Traits;

use App\Support\Formatter;
use Brick\Money\Money;

trait FormatsMoney
{
    /**
     * Format a money value from minor units to a 2dp decimal string for API responses.
     *
     * Uses brick/money minor-unit conversion (not float division) so large integer
     * amounts never accrue binary-float drift. The method has no currency context,
     * so it renders the project's default 2dp scale; currency-aware formatting with
     * per-currency scale (JPY 0dp, KWD 3dp) lives in {@see Formatter}.
     */
    public function formatMoneyCost(string $attribute): string
    {
        $value = (int) $this->getAttribute($attribute);

        return (string) Money::ofMinor($value, 'GBP')->getAmount()->toScale(2);
    }
}

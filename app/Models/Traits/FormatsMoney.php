<?php

namespace App\Models\Traits;

trait FormatsMoney
{
    /**
     * Format a money value from minor units to decimal string for API responses.
     */
    public function formatMoneyCost(string $attribute): string
    {
        $value = (int) $this->getAttribute($attribute);

        return number_format($value / 100, 2, '.', '');
    }
}

<?php

namespace App\ValueObjects;

/**
 * Immutable single row of a rate breakdown's granular line-item display.
 *
 * Each line item describes a contiguous range of periods charged at the same
 * adjusted unit price, e.g. "Days 3-5 at 0.5x multiplier". Monetary values are
 * stored in currency minor units.
 */
class RateLineItem
{
    /**
     * @param  int  $periodFrom  First period number this line covers (1-indexed, inclusive)
     * @param  int  $periodTo  Last period number this line covers (inclusive)
     * @param  string  $label  Human-readable description of the line
     * @param  string  $multiplier  Applied multiplier as a decimal string (e.g. "1.0", "0.5")
     * @param  int  $unitPriceMinor  Adjusted per-period unit price in minor units
     * @param  int  $lineTotalMinor  Total for this line in minor units
     */
    public function __construct(
        public readonly int $periodFrom,
        public readonly int $periodTo,
        public readonly string $label,
        public readonly string $multiplier,
        public readonly int $unitPriceMinor,
        public readonly int $lineTotalMinor,
    ) {}

    /**
     * @return array{
     *     period_from: int,
     *     period_to: int,
     *     label: string,
     *     multiplier: string,
     *     unit_price_minor: int,
     *     line_total_minor: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'period_from' => $this->periodFrom,
            'period_to' => $this->periodTo,
            'label' => $this->label,
            'multiplier' => $this->multiplier,
            'unit_price_minor' => $this->unitPriceMinor,
            'line_total_minor' => $this->lineTotalMinor,
        ];
    }
}

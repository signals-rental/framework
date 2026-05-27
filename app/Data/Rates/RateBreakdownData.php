<?php

namespace App\Data\Rates;

use App\ValueObjects\RateBreakdown;
use App\ValueObjects\RateLineItem;
use Brick\Money\Money;
use Spatie\LaravelData\Data;

/**
 * API/UI serialisation of a {@see RateBreakdown}. All monetary values are
 * rendered as decimal strings (e.g. "2664.00") from the breakdown's integer
 * minor units, and line items expose structured fields plus formatted money so
 * consumers can build human-readable descriptions without seeing raw minor units.
 */
class RateBreakdownData extends Data
{
    /**
     * @param  list<array{period_from: int, period_to: int, multiplier: string, unit_price: string, line_total: string}>  $line_items
     * @param  list<array{key: string, label: string, description: string, before: string, after: string}>  $applied_modifiers
     */
    public function __construct(
        public string $currency,
        public string $unit_price,
        public int $units,
        public string $unit_label,
        public string $per_unit_subtotal,
        public int $quantity,
        public string $total,
        public array $line_items,
        public array $applied_modifiers,
    ) {}

    public static function fromBreakdown(RateBreakdown $breakdown): self
    {
        $currency = $breakdown->currency;

        return new self(
            currency: $currency,
            unit_price: self::money($breakdown->unitPriceMinor, $currency),
            units: $breakdown->units,
            unit_label: $breakdown->unitLabel,
            per_unit_subtotal: self::money($breakdown->perUnitSubtotalMinor, $currency),
            quantity: $breakdown->quantity,
            total: self::money($breakdown->totalMinor(), $currency),
            line_items: array_map(static fn (RateLineItem $item): array => [
                'period_from' => $item->periodFrom,
                'period_to' => $item->periodTo,
                'multiplier' => $item->multiplier,
                'unit_price' => self::money($item->unitPriceMinor, $currency),
                'line_total' => self::money($item->lineTotalMinor, $currency),
            ], $breakdown->lineItems),
            applied_modifiers: array_map(static fn (array $modifier): array => [
                'key' => $modifier['key'],
                'label' => $modifier['label'],
                'description' => $modifier['description'],
                'before' => self::money($modifier['beforeMinor'], $currency),
                'after' => self::money($modifier['afterMinor'], $currency),
            ], $breakdown->appliedModifiers),
        );
    }

    /**
     * Render a minor-unit amount as a decimal string in the given currency.
     */
    private static function money(int $minor, string $currency): string
    {
        return (string) Money::ofMinor($minor, $currency)->getAmount();
    }
}

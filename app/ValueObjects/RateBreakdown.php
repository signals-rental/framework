<?php

namespace App\ValueObjects;

/**
 * Immutable audit trail of how a rental charge was derived.
 *
 * A strategy produces the base breakdown; each modifier returns a new instance
 * via {@see self::withPerUnitSubtotal()} / {@see self::withModifierApplied()}.
 * The breakdown is never mutated in place. Monetary values are stored in
 * currency minor units; {@see self::totalMinor()} is the per-unit subtotal
 * multiplied by quantity.
 */
class RateBreakdown
{
    /**
     * @param  int  $unitPriceMinor  Base per-unit price in minor units
     * @param  string  $currency  ISO 4217 currency code
     * @param  int  $units  Number of chargeable units (e.g. days) in the period
     * @param  string  $unitLabel  Human-readable unit label (e.g. "days", "hours")
     * @param  int  $perUnitSubtotalMinor  Subtotal for a single quantity in minor units
     * @param  int  $quantity  Number of units being charged
     * @param  array<int, RateLineItem>  $lineItems  Granular per-period line items
     * @param  array<int, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     beforeMinor: int,
     *     afterMinor: int,
     * }>  $appliedModifiers  Ordered record of each modifier and its effect
     */
    public function __construct(
        public readonly int $unitPriceMinor,
        public readonly string $currency,
        public readonly int $units,
        public readonly string $unitLabel,
        public readonly int $perUnitSubtotalMinor,
        public readonly int $quantity,
        public readonly array $lineItems,
        public readonly array $appliedModifiers,
    ) {}

    /**
     * Final charge in minor units: the per-unit subtotal multiplied by quantity.
     */
    public function totalMinor(): int
    {
        return $this->perUnitSubtotalMinor * $this->quantity;
    }

    /**
     * Return a new breakdown with an updated per-unit subtotal and line items,
     * leaving this instance untouched.
     *
     * @param  array<int, RateLineItem>  $lineItems
     */
    public function withPerUnitSubtotal(int $minor, array $lineItems): self
    {
        return new self(
            unitPriceMinor: $this->unitPriceMinor,
            currency: $this->currency,
            units: $this->units,
            unitLabel: $this->unitLabel,
            perUnitSubtotalMinor: $minor,
            quantity: $this->quantity,
            lineItems: $lineItems,
            appliedModifiers: $this->appliedModifiers,
        );
    }

    /**
     * Return a new breakdown with the given modifier appended to the applied
     * list, leaving this instance untouched.
     *
     * @param  array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     beforeMinor: int,
     *     afterMinor: int,
     * }  $modifier
     */
    public function withModifierApplied(array $modifier): self
    {
        return new self(
            unitPriceMinor: $this->unitPriceMinor,
            currency: $this->currency,
            units: $this->units,
            unitLabel: $this->unitLabel,
            perUnitSubtotalMinor: $this->perUnitSubtotalMinor,
            quantity: $this->quantity,
            lineItems: $this->lineItems,
            appliedModifiers: [...$this->appliedModifiers, $modifier],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'unit_price_minor' => $this->unitPriceMinor,
            'currency' => $this->currency,
            'units' => $this->units,
            'unit_label' => $this->unitLabel,
            'per_unit_subtotal_minor' => $this->perUnitSubtotalMinor,
            'quantity' => $this->quantity,
            'total_minor' => $this->totalMinor(),
            'line_items' => array_map(static fn (RateLineItem $item): array => $item->toArray(), $this->lineItems),
            'applied_modifiers' => $this->appliedModifiers,
        ];
    }
}

<?php

namespace App\Services\RateEngine\Modifiers;

use App\Contracts\RateModifier;
use App\Support\ConfigSchema\Fields\DecimalField;
use App\Support\ConfigSchema\Fields\RepeaterField;
use App\Support\ConfigSchema\Schema;
use App\ValueObjects\CalculationContext;
use App\ValueObjects\RateBreakdown;
use App\ValueObjects\RateLineItem;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Adjusts the per-period unit price using a positional table of multipliers:
 * the Nth configured tier applies to period N, and the final defined tier
 * inherits forward to every remaining period (Current RMS behaviour). Maps to
 * CRMS's "Daily Multiplier" style engines.
 *
 * Config (under the `multiplier` key of a rate definition):
 *  - tiers (list<array{multiplier: string}>): ordered per-period multipliers as
 *    decimal strings, e.g. `[{multiplier: "1.0"}, {multiplier: "0.7"}, {multiplier: "0.5"}]`.
 *
 * The modifier rewrites the breakdown's line items, grouping contiguous periods
 * that share a multiplier into a single line, and records its before/after
 * per-unit subtotal in the breakdown's audit trail. Each tier's adjusted unit
 * price is rounded to minor units (HALF_UP) so the line items reconcile exactly
 * with the per-unit subtotal.
 */
class MultiplierModifier implements RateModifier
{
    public function identifier(): string
    {
        return 'multiplier';
    }

    public function label(): string
    {
        return 'Multiplier';
    }

    public function priority(): int
    {
        return 100;
    }

    public function configSchema(): Schema
    {
        return Schema::make(
            RepeaterField::make('tiers')->label('Tiers')->minItems(1)->fields(
                DecimalField::make('multiplier')->label('Multiplier')->required()->default('1.0'),
            ),
        );
    }

    public function apply(RateBreakdown $breakdown, array $config, CalculationContext $context): RateBreakdown
    {
        $multipliers = $this->multipliersPerPeriod($config, $breakdown->units);

        $lineItems = [];
        $perUnitSubtotalMinor = 0;
        $index = 0;

        foreach ($this->groupContiguous($multipliers) as $group) {
            $adjustedUnitPriceMinor = $this->scale($breakdown->unitPriceMinor, $group['multiplier'], $breakdown->currency);
            $periodCount = $group['to'] - $group['from'] + 1;
            $lineTotalMinor = $adjustedUnitPriceMinor * $periodCount;
            $perUnitSubtotalMinor += $lineTotalMinor;

            $lineItems[$index++] = new RateLineItem(
                periodFrom: $group['from'],
                periodTo: $group['to'],
                label: sprintf(
                    '%s %d-%d × %s',
                    ucfirst($breakdown->unitLabel),
                    $group['from'],
                    $group['to'],
                    $group['multiplier'],
                ),
                multiplier: $group['multiplier'],
                unitPriceMinor: $adjustedUnitPriceMinor,
                lineTotalMinor: $lineTotalMinor,
            );
        }

        return $breakdown
            ->withPerUnitSubtotal($perUnitSubtotalMinor, $lineItems)
            ->withModifierApplied([
                'key' => $this->identifier(),
                'label' => $this->label(),
                'description' => sprintf('Per-period multipliers applied across %d %s', $breakdown->units, $breakdown->unitLabel),
                'beforeMinor' => $breakdown->perUnitSubtotalMinor,
                'afterMinor' => $perUnitSubtotalMinor,
            ]);
    }

    /**
     * Expand the configured tiers into one multiplier per period, inheriting the
     * last defined tier forward. Falls back to "1.0" when no tiers are defined.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string> 1-indexed period number => multiplier string
     */
    private function multipliersPerPeriod(array $config, int $units): array
    {
        $tiers = is_array($config['tiers'] ?? null) ? array_values($config['tiers']) : [];

        $values = [];

        foreach ($tiers as $tier) {
            $multiplier = is_array($tier) ? ($tier['multiplier'] ?? null) : $tier;

            if (is_string($multiplier) || is_int($multiplier) || is_float($multiplier)) {
                $values[] = (string) $multiplier;
            }
        }

        $perPeriod = [];

        $last = $values === [] ? '1.0' : (string) end($values);

        for ($period = 1; $period <= $units; $period++) {
            $perPeriod[$period] = $values[$period - 1] ?? $last;
        }

        return $perPeriod;
    }

    /**
     * Collapse a per-period multiplier map into contiguous ranges sharing the
     * same multiplier.
     *
     * @param  array<int, string>  $multipliers
     * @return array<int, array{from: int, to: int, multiplier: string}>
     */
    private function groupContiguous(array $multipliers): array
    {
        $groups = [];

        foreach ($multipliers as $period => $multiplier) {
            $last = end($groups);

            if ($last !== false && $last['multiplier'] === $multiplier && $last['to'] === $period - 1) {
                $groups[array_key_last($groups)]['to'] = $period;

                continue;
            }

            $groups[] = ['from' => $period, 'to' => $period, 'multiplier' => $multiplier];
        }

        return $groups;
    }

    /**
     * Multiply a minor-unit amount by a decimal string, rounding HALF_UP to the
     * currency's minor unit.
     */
    private function scale(int $minor, string $multiplier, string $currency): int
    {
        return Money::ofMinor($minor, $currency)
            ->multipliedBy($multiplier, RoundingMode::HALF_UP)
            ->getMinorAmount()
            ->toInt();
    }
}

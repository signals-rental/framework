<?php

namespace App\Services\RateEngine\Modifiers;

use App\Contracts\RateModifier;
use App\Support\ConfigSchema\Fields\DecimalField;
use App\Support\ConfigSchema\Fields\NumberField;
use App\Support\ConfigSchema\Fields\RepeaterField;
use App\Support\ConfigSchema\Schema;
use App\ValueObjects\CalculationContext;
use App\ValueObjects\RateBreakdown;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Scales the per-unit subtotal by a factor chosen from the rental quantity: the
 * larger the order, the lower the per-unit charge. Maps to CRMS's quantity
 * "factor" tables. The factor applies to the post-multiplier per-unit subtotal,
 * and the breakdown total remains per-unit subtotal × quantity.
 *
 * Config (under the `factor` key of a rate definition):
 *  - ranges (list<array{from: int, to: ?int, factor: string}>): inclusive
 *    quantity bands with a decimal-string factor. A null `to` makes the band
 *    open-ended, e.g. `[{from: 1, to: 5, factor: "1.0"}, {from: 21, to: null, factor: "0.8"}]`.
 *
 * When the quantity matches no band the factor defaults to 1.0 (no scaling).
 * The scaled subtotal is rounded HALF_UP to minor units. Line items are left
 * untouched; the factor's effect is recorded in the breakdown's audit trail.
 */
class FactorModifier implements RateModifier
{
    public function identifier(): string
    {
        return 'factor';
    }

    public function label(): string
    {
        return 'Factor';
    }

    public function priority(): int
    {
        return 200;
    }

    public function configSchema(): Schema
    {
        return Schema::make(
            RepeaterField::make('ranges')->label('Ranges')->minItems(1)->fields(
                NumberField::make('from')->label('From')->required()->min(1)->default(1),
                NumberField::make('to')->label('To')->min(1),
                DecimalField::make('factor')->label('Factor')->required()->default('1.0'),
            ),
        );
    }

    public function apply(RateBreakdown $breakdown, array $config, CalculationContext $context): RateBreakdown
    {
        $factor = $this->factorForQuantity($config, $context->quantity);

        $scaledMinor = Money::ofMinor($breakdown->perUnitSubtotalMinor, $breakdown->currency)
            ->multipliedBy($factor, RoundingMode::HALF_UP)
            ->getMinorAmount()
            ->toInt();

        return $breakdown
            ->withPerUnitSubtotal($scaledMinor, $breakdown->lineItems)
            ->withModifierApplied([
                'key' => $this->identifier(),
                'label' => $this->label(),
                'description' => sprintf('Quantity %d × factor %s', $context->quantity, $factor),
                'beforeMinor' => $breakdown->perUnitSubtotalMinor,
                'afterMinor' => $scaledMinor,
            ]);
    }

    /**
     * Find the factor whose inclusive quantity band contains the given quantity,
     * defaulting to "1.0" when none matches.
     *
     * @param  array<string, mixed>  $config
     */
    private function factorForQuantity(array $config, int $quantity): string
    {
        $ranges = is_array($config['ranges'] ?? null) ? $config['ranges'] : [];

        foreach ($ranges as $range) {
            if (! is_array($range)) {
                continue;
            }

            $from = (int) ($range['from'] ?? 1);
            $to = isset($range['to']) ? (int) $range['to'] : null;

            if ($quantity >= $from && ($to === null || $quantity <= $to)) {
                $factor = $range['factor'] ?? '1.0';

                return (string) (is_scalar($factor) ? $factor : '1.0');
            }
        }

        return '1.0';
    }
}

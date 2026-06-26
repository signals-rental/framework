<?php

namespace App\Data\Casts;

use App\Services\CurrencyService;
use App\Services\Opportunities\OpportunityItemChargeBounds;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Normalises money input to integer minor units (pence, cents, fils).
 *
 * The project stores all money as integer minor units. API clients, however,
 * may submit money in two distinct shapes, and the type disambiguates intent:
 *
 *  - **PHP `int`** → already minor units → passed through unchanged.
 *    `12550` (GBP) stays `12550`. Keeps existing integer API clients
 *    backward-compatible with the prior `'price' => integer` contract.
 *
 *  - **string or float** → major units (a decimal amount) → converted to minor
 *    units against the row's currency scale via `brick/money`.
 *    `"125.50"` / `125.50` (GBP, scale 2) → `12550`.
 *    `"125"` / `125.0` (JPY, scale 0) → `125`.
 *    `"1.234"` (KWD, scale 3) → `1234`.
 *
 * The sibling `currency` value in the input array supplies the scale. When
 * absent it falls back to the system base currency
 * (`settings('company.base_currency')`, defaulting to `GBP`).
 *
 * **Precision is never silently rounded.** Input carrying more decimal places
 * than the currency permits (e.g. `"1.999"` for GBP) is rejected: `brick/money`
 * throws, which is translated into a `ValidationException` so it surfaces as a
 * 422 with a field-scoped error message rather than a raw 500.
 */
class MoneyInput implements Cast
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  CreationContext<Data>  $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): int
    {
        // Integers are treated as already being in minor units — pass through.
        if (is_int($value)) {
            $this->assertWithinIntegerBounds($property->name, $value);

            return $value;
        }

        $currency = $this->resolveCurrency($properties);

        try {
            $minor = Money::of($value, $currency)->getMinorAmount()->toInt();
        } catch (RoundingNecessaryException $e) {
            throw ValidationException::withMessages([
                $property->name => __('The :attribute has more decimal places than the :currency currency allows.', [
                    'attribute' => $property->name,
                    'currency' => $currency,
                ]),
            ]);
        } catch (UnknownCurrencyException|MoneyMismatchException|NumberFormatException $e) {
            throw ValidationException::withMessages([
                $property->name => __('The :attribute is not a valid money amount.', [
                    'attribute' => $property->name,
                ]),
            ]);
        }

        $this->assertWithinIntegerBounds($property->name, $minor);

        return $minor;
    }

    private function assertWithinIntegerBounds(string $field, int $minor): void
    {
        if ($minor < 0 || $minor > OpportunityItemChargeBounds::MAX_MINOR) {
            throw ValidationException::withMessages([
                $field => __('The :attribute exceeds the maximum allowed value.', [
                    'attribute' => $field,
                ]),
            ]);
        }
    }

    /**
     * Resolve the currency code from the sibling `currency` input, falling back
     * to the system base currency.
     *
     * @param  array<string, mixed>  $properties
     */
    private function resolveCurrency(array $properties): string
    {
        $currency = $properties['currency'] ?? null;

        if (is_string($currency) && $currency !== '') {
            return strtoupper($currency);
        }

        $base = app(CurrencyService::class)->baseCurrencyCode();

        return strtoupper($base);
    }
}

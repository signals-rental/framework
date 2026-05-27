<?php

use App\Contracts\RateModifier;
use App\Enums\BasePeriod;
use App\Enums\RateTransactionType;
use App\Services\RateEngine\Modifiers\FactorModifier;
use App\ValueObjects\CalculationContext;
use App\ValueObjects\RateBreakdown;
use App\ValueObjects\RateLineItem;
use Illuminate\Support\Carbon;

/**
 * A post-multiplier breakdown of `$perUnitSubtotalMinor`, as the multiplier
 * modifier would leave it before the factor modifier runs.
 */
function postMultiplierBreakdown(int $perUnitSubtotalMinor = 37000, int $quantity = 1): RateBreakdown
{
    return new RateBreakdown(
        unitPriceMinor: 10000,
        currency: 'GBP',
        units: 5,
        unitLabel: 'days',
        perUnitSubtotalMinor: $perUnitSubtotalMinor,
        quantity: $quantity,
        lineItems: [
            new RateLineItem(1, 5, '5 days', '1.0', 10000, $perUnitSubtotalMinor),
        ],
        appliedModifiers: [],
    );
}

function factorContext(int $quantity = 1): CalculationContext
{
    return new CalculationContext(
        unitPriceMinor: 10000,
        currency: 'GBP',
        start: Carbon::parse('2026-01-05 00:00:00'),
        end: Carbon::parse('2026-01-10 00:00:00'),
        quantity: $quantity,
        basePeriod: BasePeriod::Daily,
        strategyConfig: [],
        transactionType: RateTransactionType::Rental,
    );
}

/**
 * @return array<string, mixed>
 */
function exampleOneFactorConfig(): array
{
    return ['ranges' => [
        ['from' => 1, 'to' => 5, 'factor' => '1.0'],
        ['from' => 6, 'to' => 20, 'factor' => '0.9'],
        ['from' => 21, 'to' => null, 'factor' => '0.8'],
    ]];
}

it('implements the rate modifier contract', function () {
    expect(new FactorModifier)->toBeInstanceOf(RateModifier::class);
});

it('declares its identifier, label and priority', function () {
    $modifier = new FactorModifier;

    expect($modifier->identifier())->toBe('factor')
        ->and($modifier->label())->toBe('Factor')
        ->and($modifier->priority())->toBe(200);
});

it('scales the per-unit subtotal by the matching quantity range (spec example 1 step 3)', function () {
    // Quantity 8 falls in the 6-20 range -> factor 0.9. 37000 * 0.9 = 33300.
    $breakdown = (new FactorModifier)->apply(postMultiplierBreakdown(), exampleOneFactorConfig(), factorContext(quantity: 8));

    expect($breakdown->perUnitSubtotalMinor)->toBe(33300);
});

it('records its before/after effect in the applied modifiers audit trail', function () {
    $breakdown = (new FactorModifier)->apply(postMultiplierBreakdown(), exampleOneFactorConfig(), factorContext(quantity: 8));

    expect($breakdown->appliedModifiers)->toHaveCount(1);

    $applied = $breakdown->appliedModifiers[0];

    expect($applied['key'])->toBe('factor')
        ->and($applied['label'])->toBe('Factor')
        ->and($applied['beforeMinor'])->toBe(37000)
        ->and($applied['afterMinor'])->toBe(33300);
});

it('carries the scaled subtotal through to the breakdown total', function () {
    $breakdown = (new FactorModifier)->apply(postMultiplierBreakdown(quantity: 8), exampleOneFactorConfig(), factorContext(quantity: 8));

    expect($breakdown->quantity)->toBe(8)
        ->and($breakdown->totalMinor())->toBe(266400); // £2,664.00
});

it('matches an open-ended range when the quantity exceeds every upper bound', function () {
    // Quantity 50 -> 21+ range -> factor 0.8. 37000 * 0.8 = 29600.
    $breakdown = (new FactorModifier)->apply(postMultiplierBreakdown(), exampleOneFactorConfig(), factorContext(quantity: 50));

    expect($breakdown->perUnitSubtotalMinor)->toBe(29600);
});

it('leaves the subtotal untouched when the factor is 1.0', function () {
    $breakdown = (new FactorModifier)->apply(postMultiplierBreakdown(), exampleOneFactorConfig(), factorContext(quantity: 3));

    expect($breakdown->perUnitSubtotalMinor)->toBe(37000)
        ->and($breakdown->appliedModifiers[0]['beforeMinor'])->toBe(37000)
        ->and($breakdown->appliedModifiers[0]['afterMinor'])->toBe(37000);
});

it('applies a factor of 1.0 when the quantity matches no configured range', function () {
    // Ranges start at 5; quantity 2 matches nothing -> no scaling.
    $config = ['ranges' => [
        ['from' => 5, 'to' => 10, 'factor' => '0.9'],
    ]];

    $breakdown = (new FactorModifier)->apply(postMultiplierBreakdown(), $config, factorContext(quantity: 2));

    expect($breakdown->perUnitSubtotalMinor)->toBe(37000);
});

it('treats an empty range list as a no-op', function () {
    $breakdown = (new FactorModifier)->apply(postMultiplierBreakdown(), ['ranges' => []], factorContext(quantity: 8));

    expect($breakdown->perUnitSubtotalMinor)->toBe(37000);
});

it('rounds the scaled subtotal half up', function () {
    // 12345 * 0.9 = 11110.5 -> 11111 (HALF_UP).
    $config = ['ranges' => [['from' => 1, 'to' => null, 'factor' => '0.9']]];

    $breakdown = (new FactorModifier)->apply(postMultiplierBreakdown(perUnitSubtotalMinor: 12345), $config, factorContext(quantity: 1));

    expect($breakdown->perUnitSubtotalMinor)->toBe(11111);
});

it('skips malformed (non-array) range entries and uses the next valid one', function () {
    $config = ['ranges' => [
        'not-a-range',
        ['from' => 1, 'to' => null, 'factor' => '0.9'],
    ]];

    $breakdown = (new FactorModifier)->apply(postMultiplierBreakdown(), $config, factorContext(quantity: 8));

    expect($breakdown->perUnitSubtotalMinor)->toBe(33300);
});

it('falls back to a factor of 1.0 when the matching range has a non-scalar factor', function () {
    $config = ['ranges' => [
        ['from' => 1, 'to' => null, 'factor' => ['unexpected' => 'array']],
    ]];

    $breakdown = (new FactorModifier)->apply(postMultiplierBreakdown(), $config, factorContext(quantity: 8));

    expect($breakdown->perUnitSubtotalMinor)->toBe(37000);
});

it('does not modify the line items', function () {
    $breakdown = (new FactorModifier)->apply(postMultiplierBreakdown(), exampleOneFactorConfig(), factorContext(quantity: 8));

    expect($breakdown->lineItems)->toHaveCount(1)
        ->and($breakdown->lineItems[0]->lineTotalMinor)->toBe(37000);
});

<?php

use App\Contracts\RateModifier;
use App\Enums\BasePeriod;
use App\Enums\RateTransactionType;
use App\Services\RateEngine\Modifiers\MultiplierModifier;
use App\ValueObjects\CalculationContext;
use App\ValueObjects\RateBreakdown;
use App\ValueObjects\RateLineItem;
use Illuminate\Support\Carbon;

/**
 * A flat base breakdown of `$units` days at `$unitPriceMinor` each, as a
 * period strategy would produce before any modifier runs.
 */
function baseDailyBreakdown(int $units = 5, int $unitPriceMinor = 10000, int $quantity = 1): RateBreakdown
{
    return new RateBreakdown(
        unitPriceMinor: $unitPriceMinor,
        currency: 'GBP',
        units: $units,
        unitLabel: 'days',
        perUnitSubtotalMinor: $units * $unitPriceMinor,
        quantity: $quantity,
        lineItems: [
            new RateLineItem(1, $units, "{$units} days", '1.0', $unitPriceMinor, $units * $unitPriceMinor),
        ],
        appliedModifiers: [],
    );
}

/**
 * @param  array<string, mixed>  $strategyConfig
 */
function multiplierContext(int $quantity = 1, array $strategyConfig = []): CalculationContext
{
    return new CalculationContext(
        unitPriceMinor: 10000,
        currency: 'GBP',
        start: Carbon::parse('2026-01-05 00:00:00'),
        end: Carbon::parse('2026-01-10 00:00:00'),
        quantity: $quantity,
        basePeriod: BasePeriod::Daily,
        strategyConfig: $strategyConfig,
        transactionType: RateTransactionType::Rental,
    );
}

it('implements the rate modifier contract', function () {
    expect(new MultiplierModifier)->toBeInstanceOf(RateModifier::class);
});

it('declares its identifier, label and priority', function () {
    $modifier = new MultiplierModifier;

    expect($modifier->identifier())->toBe('multiplier')
        ->and($modifier->label())->toBe('Multiplier')
        ->and($modifier->priority())->toBe(100);
});

it('recomputes the per-unit subtotal by tier (spec example 1 step 2)', function () {
    // Base: 5 days @ £100. Multipliers D1=1.0 D2=1.0 D3=0.7 D4=0.5 (D5 inherits 0.5).
    $config = ['tiers' => [
        ['multiplier' => '1.0'],
        ['multiplier' => '1.0'],
        ['multiplier' => '0.7'],
        ['multiplier' => '0.5'],
    ]];

    $breakdown = (new MultiplierModifier)->apply(baseDailyBreakdown(), $config, multiplierContext());

    expect($breakdown->perUnitSubtotalMinor)->toBe(37000)
        ->and($breakdown->units)->toBe(5)
        ->and($breakdown->unitPriceMinor)->toBe(10000);
});

it('groups contiguous equal multipliers into line items', function () {
    $config = ['tiers' => [
        ['multiplier' => '1.0'],
        ['multiplier' => '1.0'],
        ['multiplier' => '0.7'],
        ['multiplier' => '0.5'],
    ]];

    $breakdown = (new MultiplierModifier)->apply(baseDailyBreakdown(), $config, multiplierContext());

    expect($breakdown->lineItems)->toHaveCount(3);

    [$first, $second, $third] = $breakdown->lineItems;

    expect($first->periodFrom)->toBe(1)
        ->and($first->periodTo)->toBe(2)
        ->and($first->multiplier)->toBe('1.0')
        ->and($first->unitPriceMinor)->toBe(10000)
        ->and($first->lineTotalMinor)->toBe(20000);

    expect($second->periodFrom)->toBe(3)
        ->and($second->periodTo)->toBe(3)
        ->and($second->multiplier)->toBe('0.7')
        ->and($second->unitPriceMinor)->toBe(7000)
        ->and($second->lineTotalMinor)->toBe(7000);

    expect($third->periodFrom)->toBe(4)
        ->and($third->periodTo)->toBe(5)
        ->and($third->multiplier)->toBe('0.5')
        ->and($third->unitPriceMinor)->toBe(5000)
        ->and($third->lineTotalMinor)->toBe(10000);
});

it('records its effect in the applied modifiers audit trail', function () {
    $config = ['tiers' => [
        ['multiplier' => '1.0'],
        ['multiplier' => '1.0'],
        ['multiplier' => '0.7'],
        ['multiplier' => '0.5'],
    ]];

    $breakdown = (new MultiplierModifier)->apply(baseDailyBreakdown(), $config, multiplierContext());

    expect($breakdown->appliedModifiers)->toHaveCount(1);

    $applied = $breakdown->appliedModifiers[0];

    expect($applied['key'])->toBe('multiplier')
        ->and($applied['label'])->toBe('Multiplier')
        ->and($applied['beforeMinor'])->toBe(50000)
        ->and($applied['afterMinor'])->toBe(37000);
});

it('inherits the last defined tier forward beyond the configured rows', function () {
    // Only one tier of 0.5 defined; all 5 days should take 0.5.
    $config = ['tiers' => [['multiplier' => '0.5']]];

    $breakdown = (new MultiplierModifier)->apply(baseDailyBreakdown(), $config, multiplierContext());

    expect($breakdown->perUnitSubtotalMinor)->toBe(25000)
        ->and($breakdown->lineItems)->toHaveCount(1);

    $line = $breakdown->lineItems[0];

    expect($line->periodFrom)->toBe(1)
        ->and($line->periodTo)->toBe(5)
        ->and($line->multiplier)->toBe('0.5')
        ->and($line->unitPriceMinor)->toBe(5000)
        ->and($line->lineTotalMinor)->toBe(25000);
});

it('uses only as many tiers as there are units', function () {
    // 5 tiers defined but only 2 units; days 3-5 do not exist.
    $config = ['tiers' => [
        ['multiplier' => '1.0'],
        ['multiplier' => '0.9'],
        ['multiplier' => '0.8'],
        ['multiplier' => '0.7'],
        ['multiplier' => '0.6'],
    ]];

    $breakdown = (new MultiplierModifier)->apply(baseDailyBreakdown(units: 2), $config, multiplierContext());

    expect($breakdown->units)->toBe(2)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(19000) // 10000 + 9000
        ->and($breakdown->lineItems)->toHaveCount(2);
});

it('rounds fractional per-day prices half up', function () {
    // £3.33 per day, multiplier 0.7 -> 233.1 -> 233 (rounded), x1 day.
    $config = ['tiers' => [['multiplier' => '0.7']]];

    $breakdown = (new MultiplierModifier)->apply(
        baseDailyBreakdown(units: 1, unitPriceMinor: 333),
        $config,
        multiplierContext(),
    );

    expect($breakdown->lineItems[0]->unitPriceMinor)->toBe(233)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(233);
});

it('keeps line items reconciled with the per-unit subtotal when rounding bites', function () {
    // £3.33/day with a 0.5 tier rounds each day to 167 (332.5 -> 333 wait: 333*0.5=166.5 -> 167).
    // Per-tier rounding: a 4-day group at 0.5 = 167 × 4 = 668, and the per-unit
    // subtotal must equal the sum of the line totals (no drift).
    $config = ['tiers' => [['multiplier' => '0.5']]];

    $breakdown = (new MultiplierModifier)->apply(
        baseDailyBreakdown(units: 4, unitPriceMinor: 333),
        $config,
        multiplierContext(),
    );

    $lineTotalSum = array_sum(array_map(fn ($line) => $line->lineTotalMinor, $breakdown->lineItems));

    expect($breakdown->lineItems[0]->unitPriceMinor)->toBe(167)
        ->and($breakdown->lineItems[0]->lineTotalMinor)->toBe(668)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(668)
        ->and($breakdown->perUnitSubtotalMinor)->toBe($lineTotalSum);
});

it('treats an empty tier list as a no-op multiplier of 1.0', function () {
    $breakdown = (new MultiplierModifier)->apply(baseDailyBreakdown(), ['tiers' => []], multiplierContext());

    expect($breakdown->perUnitSubtotalMinor)->toBe(50000)
        ->and($breakdown->lineItems)->toHaveCount(1)
        ->and($breakdown->lineItems[0]->multiplier)->toBe('1.0');
});

it('preserves quantity on the breakdown without folding it into the subtotal', function () {
    $config = ['tiers' => [['multiplier' => '0.5']]];

    $breakdown = (new MultiplierModifier)->apply(
        baseDailyBreakdown(quantity: 8),
        $config,
        multiplierContext(quantity: 8),
    );

    expect($breakdown->quantity)->toBe(8)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(25000)
        ->and($breakdown->totalMinor())->toBe(200000);
});

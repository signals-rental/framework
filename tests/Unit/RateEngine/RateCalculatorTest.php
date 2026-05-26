<?php

use App\Enums\BasePeriod;
use App\Enums\RateTransactionType;
use App\Services\RateEngine\Modifiers\FactorModifier;
use App\Services\RateEngine\Modifiers\MultiplierModifier;
use App\Services\RateEngine\RateCalculator;
use App\Services\RateEngine\RateEngineRegistry;
use App\Services\RateEngine\Strategies\FixedStrategy;
use App\Services\RateEngine\Strategies\HybridStrategy;
use App\Services\RateEngine\Strategies\PeriodStrategy;
use App\ValueObjects\CalculationContext;
use Illuminate\Support\Carbon;

function rateCalculator(): RateCalculator
{
    $registry = new RateEngineRegistry;
    $registry->registerStrategy(new PeriodStrategy);
    $registry->registerStrategy(new FixedStrategy);
    $registry->registerStrategy(new HybridStrategy);
    $registry->registerModifier(new MultiplierModifier);
    $registry->registerModifier(new FactorModifier);

    return new RateCalculator($registry);
}

/**
 * @param  array<string, mixed>  $strategyConfig
 */
function calcContext(
    int $unitPriceMinor = 10000,
    string $start = '2026-01-05 00:00:00',
    string $end = '2026-01-10 00:00:00',
    ?BasePeriod $basePeriod = BasePeriod::Daily,
    int $quantity = 1,
    array $strategyConfig = [],
): CalculationContext {
    return new CalculationContext(
        unitPriceMinor: $unitPriceMinor,
        currency: 'GBP',
        start: Carbon::parse($start),
        end: Carbon::parse($end),
        quantity: $quantity,
        basePeriod: $basePeriod,
        strategyConfig: $strategyConfig,
        transactionType: RateTransactionType::Rental,
    );
}

/**
 * @return array<string, mixed>
 */
function exampleOneMultiplierConfig(): array
{
    return ['tiers' => [
        ['multiplier' => '1.0'],
        ['multiplier' => '1.0'],
        ['multiplier' => '0.7'],
        ['multiplier' => '0.5'],
    ]];
}

/**
 * @return array<string, mixed>
 */
function exampleOneFactorConfigForCalc(): array
{
    return ['ranges' => [
        ['from' => 1, 'to' => 5, 'factor' => '1.0'],
        ['from' => 6, 'to' => 20, 'factor' => '0.9'],
        ['from' => 21, 'to' => null, 'factor' => '0.8'],
    ]];
}

it('runs strategy then modifiers end-to-end for spec example 1 (£2,664.00)', function () {
    // Daily £100, 5 days, qty 8, multiplier (1.0/1.0/0.7/0.5) + factor (qty 8 -> 0.9).
    $breakdown = rateCalculator()->calculate(
        context: calcContext(quantity: 8),
        strategy: 'period',
        enabledModifiers: ['multiplier', 'factor'],
        modifierConfigs: [
            'multiplier' => exampleOneMultiplierConfig(),
            'factor' => exampleOneFactorConfigForCalc(),
        ],
    );

    expect($breakdown->units)->toBe(5)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(33300)
        ->and($breakdown->quantity)->toBe(8)
        ->and($breakdown->totalMinor())->toBe(266400);
});

it('records each applied modifier in order with its before/after subtotal (example 1)', function () {
    $breakdown = rateCalculator()->calculate(
        context: calcContext(quantity: 8),
        strategy: 'period',
        enabledModifiers: ['multiplier', 'factor'],
        modifierConfigs: [
            'multiplier' => exampleOneMultiplierConfig(),
            'factor' => exampleOneFactorConfigForCalc(),
        ],
    );

    expect($breakdown->appliedModifiers)->toHaveCount(2);

    [$multiplier, $factor] = $breakdown->appliedModifiers;

    expect($multiplier['key'])->toBe('multiplier')
        ->and($multiplier['beforeMinor'])->toBe(50000)
        ->and($multiplier['afterMinor'])->toBe(37000);

    expect($factor['key'])->toBe('factor')
        ->and($factor['beforeMinor'])->toBe(37000)
        ->and($factor['afterMinor'])->toBe(33300);
});

it('runs the hybrid strategy with no modifiers for spec example 2 (£360.00)', function () {
    // Hybrid: fixed £200 / 3 days, subs £40/day, 7 days, qty 1.
    $breakdown = rateCalculator()->calculate(
        context: calcContext(
            unitPriceMinor: 4000,
            start: '2026-01-05 00:00:00',
            end: '2026-01-12 00:00:00', // 7 days
            quantity: 1,
            strategyConfig: ['fixed_charge' => 20000, 'fixed_period_units' => 3],
        ),
        strategy: 'hybrid',
    );

    expect($breakdown->units)->toBe(7)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(36000)
        ->and($breakdown->totalMinor())->toBe(36000)
        ->and($breakdown->appliedModifiers)->toBe([]);
});

it('applies modifiers in registry priority order regardless of the enabled list order', function () {
    // Enable factor before multiplier; the engine must still run multiplier first.
    $breakdown = rateCalculator()->calculate(
        context: calcContext(quantity: 8),
        strategy: 'period',
        enabledModifiers: ['factor', 'multiplier'],
        modifierConfigs: [
            'multiplier' => exampleOneMultiplierConfig(),
            'factor' => exampleOneFactorConfigForCalc(),
        ],
    );

    expect(array_column($breakdown->appliedModifiers, 'key'))->toBe(['multiplier', 'factor'])
        ->and($breakdown->totalMinor())->toBe(266400);
});

it('only applies modifiers that are enabled', function () {
    // Only the multiplier is enabled; the factor config is present but ignored.
    $breakdown = rateCalculator()->calculate(
        context: calcContext(quantity: 8),
        strategy: 'period',
        enabledModifiers: ['multiplier'],
        modifierConfigs: [
            'multiplier' => exampleOneMultiplierConfig(),
            'factor' => exampleOneFactorConfigForCalc(),
        ],
    );

    expect($breakdown->appliedModifiers)->toHaveCount(1)
        ->and($breakdown->appliedModifiers[0]['key'])->toBe('multiplier')
        ->and($breakdown->perUnitSubtotalMinor)->toBe(37000);
});

it('returns the bare strategy breakdown when no modifiers are enabled', function () {
    $breakdown = rateCalculator()->calculate(
        context: calcContext(),
        strategy: 'period',
    );

    expect($breakdown->perUnitSubtotalMinor)->toBe(50000)
        ->and($breakdown->appliedModifiers)->toBe([]);
});

it('passes an empty config to an enabled modifier that has none configured', function () {
    // Multiplier enabled with no config -> no-op 1.0 across all periods.
    $breakdown = rateCalculator()->calculate(
        context: calcContext(),
        strategy: 'period',
        enabledModifiers: ['multiplier'],
    );

    expect($breakdown->perUnitSubtotalMinor)->toBe(50000)
        ->and($breakdown->appliedModifiers)->toHaveCount(1);
});

it('throws when the strategy is not registered', function () {
    expect(fn () => rateCalculator()->calculate(calcContext(), 'nope'))
        ->toThrow(InvalidArgumentException::class, 'No calculation strategy registered for [nope].');
});

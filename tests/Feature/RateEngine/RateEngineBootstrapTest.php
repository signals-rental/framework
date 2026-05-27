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

it('binds the rate engine registry as a singleton', function () {
    expect(app(RateEngineRegistry::class))->toBe(app(RateEngineRegistry::class));
});

it('bootstraps the registry with the three core strategies', function () {
    $registry = app(RateEngineRegistry::class);

    expect($registry->strategy('period'))->toBeInstanceOf(PeriodStrategy::class)
        ->and($registry->strategy('fixed'))->toBeInstanceOf(FixedStrategy::class)
        ->and($registry->strategy('hybrid'))->toBeInstanceOf(HybridStrategy::class);
});

it('bootstraps the registry with the two core modifiers in priority order', function () {
    $registry = app(RateEngineRegistry::class);

    expect($registry->modifier('multiplier'))->toBeInstanceOf(MultiplierModifier::class)
        ->and($registry->modifier('factor'))->toBeInstanceOf(FactorModifier::class)
        ->and(array_map(fn ($m) => $m->identifier(), $registry->modifiers()))->toBe(['multiplier', 'factor']);
});

it('resolves a working calculator from the container that computes spec example 1', function () {
    $context = new CalculationContext(
        unitPriceMinor: 10000,
        currency: 'GBP',
        start: Carbon::parse('2026-01-05 00:00:00'),
        end: Carbon::parse('2026-01-10 00:00:00'),
        quantity: 8,
        basePeriod: BasePeriod::Daily,
        strategyConfig: [],
        transactionType: RateTransactionType::Rental,
    );

    $breakdown = app(RateCalculator::class)->calculate(
        context: $context,
        strategy: 'period',
        enabledModifiers: ['multiplier', 'factor'],
        modifierConfigs: [
            'multiplier' => ['tiers' => [
                ['multiplier' => '1.0'],
                ['multiplier' => '1.0'],
                ['multiplier' => '0.7'],
                ['multiplier' => '0.5'],
            ]],
            'factor' => ['ranges' => [
                ['from' => 1, 'to' => 5, 'factor' => '1.0'],
                ['from' => 6, 'to' => 20, 'factor' => '0.9'],
                ['from' => 21, 'to' => null, 'factor' => '0.8'],
            ]],
        ],
    );

    expect($breakdown->totalMinor())->toBe(266400);
});

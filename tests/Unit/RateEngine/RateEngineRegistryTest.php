<?php

use App\Contracts\CalculationStrategy;
use App\Contracts\RateModifier;
use App\Services\RateEngine\Modifiers\FactorModifier;
use App\Services\RateEngine\Modifiers\MultiplierModifier;
use App\Services\RateEngine\RateEngineRegistry;
use App\Services\RateEngine\Strategies\FixedStrategy;
use App\Services\RateEngine\Strategies\HybridStrategy;
use App\Services\RateEngine\Strategies\PeriodStrategy;

it('registers and retrieves a strategy by identifier', function () {
    $registry = new RateEngineRegistry;
    $strategy = new PeriodStrategy;

    $registry->registerStrategy($strategy);

    expect($registry->strategy('period'))->toBe($strategy)
        ->and($registry->hasStrategy('period'))->toBeTrue();
});

it('returns all registered strategies keyed by identifier', function () {
    $registry = new RateEngineRegistry;
    $registry->registerStrategy(new PeriodStrategy);
    $registry->registerStrategy(new FixedStrategy);

    expect($registry->strategies())->toHaveKeys(['period', 'fixed'])
        ->and($registry->strategies()['period'])->toBeInstanceOf(PeriodStrategy::class);
});

it('reports whether a strategy is registered', function () {
    $registry = new RateEngineRegistry;
    $registry->registerStrategy(new HybridStrategy);

    expect($registry->hasStrategy('hybrid'))->toBeTrue()
        ->and($registry->hasStrategy('period'))->toBeFalse();
});

it('throws when retrieving an unregistered strategy', function () {
    $registry = new RateEngineRegistry;

    expect(fn () => $registry->strategy('missing'))
        ->toThrow(InvalidArgumentException::class, 'No calculation strategy registered for [missing].');
});

it('registers and retrieves a modifier by identifier', function () {
    $registry = new RateEngineRegistry;
    $modifier = new MultiplierModifier;

    $registry->registerModifier($modifier);

    expect($registry->modifier('multiplier'))->toBe($modifier)
        ->and($registry->hasModifier('multiplier'))->toBeTrue();
});

it('throws when retrieving an unregistered modifier', function () {
    $registry = new RateEngineRegistry;

    expect(fn () => $registry->modifier('missing'))
        ->toThrow(InvalidArgumentException::class, 'No rate modifier registered for [missing].');
});

it('returns modifiers ordered by ascending priority', function () {
    $registry = new RateEngineRegistry;
    // Register out of order; factor (200) should come after multiplier (100).
    $registry->registerModifier(new FactorModifier);
    $registry->registerModifier(new MultiplierModifier);

    $identifiers = array_map(
        static fn (RateModifier $modifier): string => $modifier->identifier(),
        $registry->modifiers(),
    );

    expect($identifiers)->toBe(['multiplier', 'factor']);
});

it('contracts the strategy and modifier types it stores', function () {
    $registry = new RateEngineRegistry;
    $registry->registerStrategy(new PeriodStrategy);
    $registry->registerModifier(new MultiplierModifier);

    expect($registry->strategy('period'))->toBeInstanceOf(CalculationStrategy::class)
        ->and($registry->modifier('multiplier'))->toBeInstanceOf(RateModifier::class);
});

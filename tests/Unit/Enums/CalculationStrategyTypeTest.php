<?php

use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;

it('has correct cases', function () {
    expect(CalculationStrategyType::cases())->toHaveCount(4);
});

it('has correct string values', function (CalculationStrategyType $type, string $expected) {
    expect($type->value)->toBe($expected);
})->with([
    [CalculationStrategyType::Period, 'period'],
    [CalculationStrategyType::Usage, 'usage'],
    [CalculationStrategyType::Fixed, 'fixed'],
    [CalculationStrategyType::Hybrid, 'hybrid'],
]);

it('returns correct labels', function (CalculationStrategyType $type, string $expected) {
    expect($type->label())->toBe($expected);
})->with([
    [CalculationStrategyType::Period, 'Period-based'],
    [CalculationStrategyType::Usage, 'Usage-based'],
    [CalculationStrategyType::Fixed, 'Fixed'],
    [CalculationStrategyType::Hybrid, 'Hybrid'],
]);

it('has a label for every case', function () {
    foreach (CalculationStrategyType::cases() as $type) {
        expect($type->label())->toBeString()->not()->toBeEmpty();
    }
});

it('allows all five base periods for the period strategy', function () {
    expect(CalculationStrategyType::Period->allowedBasePeriods())->toBe([
        BasePeriod::HalfHourly,
        BasePeriod::Hourly,
        BasePeriod::Daily,
        BasePeriod::Weekly,
        BasePeriod::Monthly,
    ]);
});

it('allows only daily for the usage strategy', function () {
    expect(CalculationStrategyType::Usage->allowedBasePeriods())->toBe([
        BasePeriod::Daily,
    ]);
});

it('allows daily, weekly and monthly for the hybrid strategy', function () {
    expect(CalculationStrategyType::Hybrid->allowedBasePeriods())->toBe([
        BasePeriod::Daily,
        BasePeriod::Weekly,
        BasePeriod::Monthly,
    ]);
});

it('allows no base periods for the fixed strategy', function () {
    expect(CalculationStrategyType::Fixed->allowedBasePeriods())->toBe([]);
});

it('reports whether a base period is required', function (CalculationStrategyType $type, bool $expected) {
    expect($type->requiresBasePeriod())->toBe($expected);
})->with([
    [CalculationStrategyType::Period, true],
    [CalculationStrategyType::Usage, true],
    [CalculationStrategyType::Hybrid, true],
    [CalculationStrategyType::Fixed, false],
]);

it('only requires a base period when at least one is allowed', function () {
    foreach (CalculationStrategyType::cases() as $type) {
        expect($type->requiresBasePeriod())->toBe($type->allowedBasePeriods() !== []);
    }
});

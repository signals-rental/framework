<?php

use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Services\RateEngine\Presets\RatePresets;

it('defines eleven CRMS-parity presets', function () {
    expect(RatePresets::all())->toHaveCount(11);
});

it('gives every preset a unique slug', function () {
    $slugs = array_column(RatePresets::all(), 'slug');

    expect($slugs)->toBe(array_unique($slugs));
});

it('omits the cut usage-based "Days Used Rate" preset', function () {
    $slugs = array_column(RatePresets::all(), 'slug');

    expect($slugs)->not->toContain('days-used-rate')
        ->and($slugs)->toContain('daily-rate'); // the documented CRMS Days Used fallback
});

it('shapes every preset with the required keys', function () {
    foreach (RatePresets::all() as $preset) {
        expect($preset)->toHaveKeys([
            'slug', 'name', 'description', 'calculation_strategy',
            'base_period', 'enabled_modifiers', 'strategy_config', 'modifier_configs',
        ])
            ->and($preset['calculation_strategy'])->toBeInstanceOf(CalculationStrategyType::class)
            ->and($preset['enabled_modifiers'])->toBeArray();
    }
});

it('maps each preset to the correct strategy, base period and modifiers', function (
    string $slug,
    CalculationStrategyType $strategy,
    ?BasePeriod $basePeriod,
    array $modifiers,
) {
    $preset = collect(RatePresets::all())->firstWhere('slug', $slug);

    expect($preset)->not->toBeNull()
        ->and($preset['calculation_strategy'])->toBe($strategy)
        ->and($preset['base_period'])->toBe($basePeriod)
        ->and($preset['enabled_modifiers'])->toBe($modifiers);
})->with([
    'daily multiplier and factor' => ['daily-multiplier-factor', CalculationStrategyType::Period, BasePeriod::Daily, ['multiplier', 'factor']],
    'daily rate' => ['daily-rate', CalculationStrategyType::Period, BasePeriod::Daily, []],
    'fixed rate and factor' => ['fixed-rate-factor', CalculationStrategyType::Fixed, null, ['factor']],
    'fixed rate and subs days' => ['fixed-rate-subs-days', CalculationStrategyType::Hybrid, BasePeriod::Daily, []],
    'fixed rate' => ['fixed-rate', CalculationStrategyType::Fixed, null, []],
    'half hourly rate' => ['half-hourly-rate', CalculationStrategyType::Period, BasePeriod::HalfHourly, []],
    'hourly multiplier and factor' => ['hourly-multiplier-factor', CalculationStrategyType::Period, BasePeriod::Hourly, ['multiplier', 'factor']],
    'hourly rate' => ['hourly-rate', CalculationStrategyType::Period, BasePeriod::Hourly, []],
    'monthly multiplier and factor' => ['monthly-multiplier-factor', CalculationStrategyType::Period, BasePeriod::Monthly, ['multiplier', 'factor']],
    'monthly rate' => ['monthly-rate', CalculationStrategyType::Period, BasePeriod::Monthly, []],
    'weekly rate' => ['weekly-rate', CalculationStrategyType::Period, BasePeriod::Weekly, []],
]);

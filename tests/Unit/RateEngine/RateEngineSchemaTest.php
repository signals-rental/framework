<?php

use App\Services\RateEngine\Modifiers\FactorModifier;
use App\Services\RateEngine\Modifiers\MultiplierModifier;
use App\Services\RateEngine\RateEngineRegistry;
use App\Services\RateEngine\Strategies\FixedStrategy;
use App\Services\RateEngine\Strategies\HybridStrategy;
use App\Services\RateEngine\Strategies\PeriodStrategy;
use App\Support\ConfigSchema\Schema;
use App\Support\ConfigSchema\Section;

function schemaRegistry(): RateEngineRegistry
{
    $registry = new RateEngineRegistry;
    $registry->registerStrategy(new PeriodStrategy);
    $registry->registerStrategy(new FixedStrategy);
    $registry->registerStrategy(new HybridStrategy);
    $registry->registerModifier(new MultiplierModifier);
    $registry->registerModifier(new FactorModifier);

    return $registry;
}

it('declares a time-options schema for the period strategy', function () {
    $schema = (new PeriodStrategy)->configSchema();

    expect($schema)->toBeInstanceOf(Schema::class)
        ->and(array_column($schema->toArray(), 'key'))->toBe([
            'day_type', 'business_hours', 'rental_days_per_week',
            'leeway_minutes', 'first_day_cutoff', 'last_day_cutoff',
        ]);
});

it('hides the business-hours group unless day_type is business', function () {
    $rules = (new PeriodStrategy)->configSchema()->validationRules(['day_type' => 'clock']);

    expect($rules)->toHaveKey('day_type')
        ->and($rules)->not->toHaveKey('business_hours_start');

    $businessRules = (new PeriodStrategy)->configSchema()->validationRules(['day_type' => 'business']);

    expect($businessRules)->toHaveKey('business_hours_start')
        ->and($businessRules)->toHaveKey('business_hours_end');
});

it('declares an empty schema for the fixed strategy', function () {
    expect((new FixedStrategy)->configSchema()->isEmpty())->toBeTrue();
});

it('declares fixed-charge fields plus time options for the hybrid strategy', function () {
    $keys = array_column((new HybridStrategy)->configSchema()->toArray(), 'key');

    expect($keys)->toContain('fixed_charge')
        ->and($keys)->toContain('fixed_period_units')
        ->and($keys)->toContain('leeway_minutes');
});

it('declares a tiers repeater for the multiplier modifier', function () {
    $rules = (new MultiplierModifier)->configSchema()->validationRules([]);

    expect($rules)->toHaveKey('tiers')
        ->and($rules)->toHaveKey('tiers.*.multiplier');
});

it('declares a ranges repeater for the factor modifier', function () {
    $rules = (new FactorModifier)->configSchema()->validationRules([]);

    expect($rules['ranges'])->toContain('array')
        ->and($rules)->toHaveKeys(['ranges.*.from', 'ranges.*.to', 'ranges.*.factor']);
});

it('composes ordered sections from a strategy and its enabled modifiers', function () {
    $sections = schemaRegistry()->composeSections('period', ['multiplier', 'factor']);

    expect($sections)->toHaveCount(3)
        ->and($sections[0])->toBeInstanceOf(Section::class)
        ->and(array_map(fn (Section $s): string => $s->key, $sections))->toBe(['options', 'multiplier', 'factor']);
});

it('orders modifier sections by registry priority regardless of enabled order', function () {
    $sections = schemaRegistry()->composeSections('period', ['factor', 'multiplier']);

    expect(array_map(fn (Section $s): string => $s->key, $sections))->toBe(['options', 'multiplier', 'factor']);
});

it('omits the options section for a strategy with no config (fixed, no modifiers)', function () {
    expect(schemaRegistry()->composeSections('fixed', []))->toBe([]);
});

it('includes only enabled modifier sections', function () {
    $sections = schemaRegistry()->composeSections('period', ['multiplier']);

    expect(array_map(fn (Section $s): string => $s->key, $sections))->toBe(['options', 'multiplier']);
});

it('sanitises modifier configs, dropping disabled modifiers and casting enabled ones', function () {
    $sanitised = schemaRegistry()->sanitiseModifierConfigs(
        ['multiplier'],
        [
            'multiplier' => ['tiers' => [['multiplier' => '0.5', 'junk' => 'x']]],
            'factor' => ['ranges' => [['from' => 1, 'to' => 5, 'factor' => '1.0']]], // disabled -> dropped
        ],
    );

    expect($sanitised)->toHaveKey('multiplier')
        ->and($sanitised)->not->toHaveKey('factor')
        ->and($sanitised['multiplier']['tiers'][0])->toBe(['multiplier' => '0.5']);
});

it('composes prefixed config validation rules for a strategy and its enabled modifiers', function () {
    $rules = schemaRegistry()->configRules(
        'period',
        ['multiplier'],
        ['day_type' => 'clock'],
        ['multiplier' => ['tiers' => []], 'factor' => ['ranges' => []]],
    );

    expect($rules)->toHaveKey('strategy_config.day_type')
        ->and($rules)->toHaveKey('strategy_config.leeway_minutes')
        ->and($rules)->toHaveKey('modifier_configs.multiplier.tiers')
        ->and($rules)->toHaveKey('modifier_configs.multiplier.tiers.*.multiplier')
        ->and($rules)->not->toHaveKey('modifier_configs.factor.ranges'); // factor disabled
});

it('sanitises strategy config, stripping hidden fields', function () {
    $sanitised = schemaRegistry()->sanitiseStrategyConfig('period', [
        'day_type' => 'clock',
        'business_hours_start' => '09:00', // hidden under clock -> dropped
        'leeway_minutes' => '30',
    ]);

    expect($sanitised)->toBe([
        'day_type' => 'clock',
        'leeway_minutes' => 30,
    ]);
});

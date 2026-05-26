<?php

use App\Data\Rates\CreateRateDefinitionData;
use App\Data\Rates\RateDefinitionData;
use App\Data\Rates\UpdateRateDefinitionData;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Models\RateDefinition;
use Illuminate\Validation\ValidationException;

it('builds a create DTO from valid input', function () {
    $data = CreateRateDefinitionData::from([
        'name' => 'Custom Daily',
        'calculation_strategy' => 'period',
        'base_period' => 'daily',
        'enabled_modifiers' => ['multiplier'],
        'strategy_config' => ['leeway_minutes' => 30],
        'modifier_configs' => ['multiplier' => ['tiers' => [['multiplier' => '0.5']]]],
    ]);

    expect($data->name)->toBe('Custom Daily')
        ->and($data->calculation_strategy)->toBe(CalculationStrategyType::Period)
        ->and($data->base_period)->toBe(BasePeriod::Daily)
        ->and($data->enabled_modifiers)->toBe(['multiplier']);
});

it('requires a name and a valid calculation strategy', function (array $payload) {
    CreateRateDefinitionData::validate($payload);
})->throws(ValidationException::class)->with([
    'missing name' => [['calculation_strategy' => 'period']],
    'invalid strategy' => [['name' => 'X', 'calculation_strategy' => 'nope']],
]);

it('allows a null base period for fixed strategies', function () {
    $data = CreateRateDefinitionData::from([
        'name' => 'Flat Fee',
        'calculation_strategy' => 'fixed',
    ]);

    expect($data->base_period)->toBeNull()
        ->and($data->enabled_modifiers)->toBe([]);
});

it('treats all update fields as optional', function () {
    $data = UpdateRateDefinitionData::from(['name' => 'Renamed']);

    expect($data->name)->toBe('Renamed')
        ->and($data->calculation_strategy)->toBeNull()
        ->and($data->strategy_config)->toBeNull();
});

it('validates update input against its rules', function () {
    expect(fn () => UpdateRateDefinitionData::validate(['calculation_strategy' => 'nope']))
        ->toThrow(ValidationException::class);

    $data = UpdateRateDefinitionData::validate(['name' => 'OK']);

    expect($data['name'])->toBe('OK');
});

it('serialises a rate definition model to a response DTO', function () {
    $definition = RateDefinition::factory()->create([
        'name' => 'Daily MF',
        'calculation_strategy' => CalculationStrategyType::Period,
        'base_period' => BasePeriod::Daily,
        'enabled_modifiers' => ['multiplier', 'factor'],
        'strategy_config' => ['leeway_minutes' => 30],
        'modifier_configs' => ['factor' => ['ranges' => []]],
    ]);

    $dto = RateDefinitionData::fromModel($definition);

    expect($dto->id)->toBe($definition->id)
        ->and($dto->name)->toBe('Daily MF')
        ->and($dto->calculation_strategy)->toBe('period')
        ->and($dto->calculation_strategy_name)->toBe('Period-based')
        ->and($dto->base_period)->toBe('daily')
        ->and($dto->base_period_name)->toBe('Daily')
        ->and($dto->enabled_modifiers)->toBe(['multiplier', 'factor'])
        ->and($dto->strategy_config)->toBe(['leeway_minutes' => 30])
        ->and($dto->modifier_configs)->toBe(['factor' => ['ranges' => []]])
        ->and($dto->is_preset)->toBeFalse()
        ->and($dto->created_at)->toEndWith('Z');
});

it('serialises a fixed definition with a null base period', function () {
    $definition = RateDefinition::factory()->fixed()->create();

    $dto = RateDefinitionData::fromModel($definition);

    expect($dto->base_period)->toBeNull()
        ->and($dto->base_period_name)->toBeNull();
});

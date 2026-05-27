<?php

use App\Contracts\HasSchema;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Models\RateDefinition;
use App\Services\SchemaBuilder;

it('persists and casts its columns', function () {
    $definition = RateDefinition::factory()->create([
        'calculation_strategy' => CalculationStrategyType::Period,
        'base_period' => BasePeriod::Daily,
        'enabled_modifiers' => ['multiplier', 'factor'],
        'strategy_config' => ['leeway_minutes' => 30],
        'modifier_configs' => ['factor' => ['ranges' => []]],
    ]);

    $fresh = $definition->fresh();

    expect($fresh->calculation_strategy)->toBe(CalculationStrategyType::Period)
        ->and($fresh->base_period)->toBe(BasePeriod::Daily)
        ->and($fresh->enabled_modifiers)->toBe(['multiplier', 'factor'])
        ->and($fresh->strategy_config)->toBe(['leeway_minutes' => 30])
        ->and($fresh->modifier_configs)->toBe(['factor' => ['ranges' => []]])
        ->and($fresh->is_preset)->toBeFalse();
});

it('allows a null base period for fixed strategies', function () {
    $definition = RateDefinition::factory()->create([
        'calculation_strategy' => CalculationStrategyType::Fixed,
        'base_period' => null,
    ]);

    expect($definition->fresh()->base_period)->toBeNull();
});

it('implements the schema contract', function () {
    expect(new RateDefinition)->toBeInstanceOf(HasSchema::class);
});

it('relates to the definition it was cloned from', function () {
    $original = RateDefinition::factory()->create();
    $copy = RateDefinition::factory()->create(['cloned_from_id' => $original->id]);

    expect($copy->clonedFrom)->not->toBeNull()
        ->and($copy->clonedFrom->id)->toBe($original->id);
});

it('has a null clonedFrom when it is not a clone', function () {
    expect(RateDefinition::factory()->create()->clonedFrom)->toBeNull();
});

it('builds a preset definition via the factory state', function () {
    $preset = RateDefinition::factory()->preset()->create();

    expect($preset->is_preset)->toBeTrue()
        ->and($preset->preset_slug)->not->toBeNull();
});

it('scopes to presets', function () {
    RateDefinition::factory()->preset()->create();
    RateDefinition::factory()->create();

    expect(RateDefinition::query()->presets()->count())->toBe(1);
});

it('scopes to custom (non-preset) definitions', function () {
    RateDefinition::factory()->preset()->create();
    RateDefinition::factory()->count(2)->create();

    expect(RateDefinition::query()->custom()->count())->toBe(2);
});

it('defines a schema', function () {
    $builder = new SchemaBuilder;

    RateDefinition::defineSchema($builder);

    $fields = $builder->build();

    expect($fields)->toHaveKeys([
        'name',
        'description',
        'calculation_strategy',
        'base_period',
        'enabled_modifiers',
        'is_preset',
        'preset_slug',
        'cloned_from_id',
        'created_at',
        'updated_at',
    ]);
});

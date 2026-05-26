<?php

use App\Actions\Rates\CreateRateDefinition;
use App\Actions\Rates\DeleteRateDefinition;
use App\Actions\Rates\DuplicateRateDefinition;
use App\Actions\Rates\UpdateRateDefinition;
use App\Data\Rates\CreateRateDefinitionData;
use App\Data\Rates\RateDefinitionData;
use App\Data\Rates\UpdateRateDefinitionData;
use App\Enums\CalculationStrategyType;
use App\Events\AuditableEvent;
use App\Models\RateDefinition;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 */
function createDefinitionData(array $overrides = []): CreateRateDefinitionData
{
    return CreateRateDefinitionData::from(array_merge([
        'name' => 'Custom Daily',
        'calculation_strategy' => 'period',
        'base_period' => 'daily',
        'enabled_modifiers' => [],
        'strategy_config' => ['day_type' => 'clock', 'leeway_minutes' => 30],
        'modifier_configs' => [],
    ], $overrides));
}

it('creates a rate definition, casting and persisting sanitised config', function () {
    Event::fake([AuditableEvent::class]);

    $result = (new CreateRateDefinition)(createDefinitionData());

    expect($result)->toBeInstanceOf(RateDefinitionData::class)
        ->and($result->name)->toBe('Custom Daily')
        ->and($result->is_preset)->toBeFalse()
        ->and($result->strategy_config['leeway_minutes'])->toBe(30); // cast to int

    $this->assertDatabaseHas('rate_definitions', ['name' => 'Custom Daily', 'is_preset' => false]);
    Event::assertDispatched(AuditableEvent::class);
});

it('strips config for hidden fields on create', function () {
    // business hours supplied while day_type is clock -> stripped by sanitisation.
    $result = (new CreateRateDefinition)(createDefinitionData([
        'strategy_config' => ['day_type' => 'clock', 'business_hours_start' => '09:00', 'leeway_minutes' => 0],
    ]));

    expect($result->strategy_config)->not->toHaveKey('business_hours_start');
});

it('validates strategy config against the composed schema', function () {
    (new CreateRateDefinition)(createDefinitionData([
        'strategy_config' => ['day_type' => 'invalid-option'],
    ]));
})->throws(ValidationException::class);

it('requires at least one tier when the multiplier modifier is enabled', function () {
    (new CreateRateDefinition)(createDefinitionData([
        'enabled_modifiers' => ['multiplier'],
        'modifier_configs' => ['multiplier' => ['tiers' => []]],
    ]));
})->throws(ValidationException::class);

it('persists valid modifier config', function () {
    $result = (new CreateRateDefinition)(createDefinitionData([
        'enabled_modifiers' => ['multiplier'],
        'modifier_configs' => ['multiplier' => ['tiers' => [['multiplier' => '0.5']]]],
    ]));

    expect($result->modifier_configs['multiplier']['tiers'][0])->toBe(['multiplier' => '0.5']);
});

it('forbids creating a rate definition without permission', function () {
    $this->actingAs(User::factory()->create()); // non-owner, no rates.create

    expect(fn () => (new CreateRateDefinition)(createDefinitionData()))
        ->toThrow(AuthorizationException::class);
});

it('updates a rate definition and strips configs for disabled modifiers', function () {
    $definition = RateDefinition::factory()->create([
        'calculation_strategy' => CalculationStrategyType::Period,
        'enabled_modifiers' => ['multiplier', 'factor'],
        'modifier_configs' => [
            'multiplier' => ['tiers' => [['multiplier' => '0.5']]],
            'factor' => ['ranges' => [['from' => 1, 'to' => 5, 'factor' => '1.0']]],
        ],
    ]);

    $result = (new UpdateRateDefinition)($definition, UpdateRateDefinitionData::from([
        'name' => 'Renamed',
        'enabled_modifiers' => ['multiplier'], // factor disabled
        'modifier_configs' => ['multiplier' => ['tiers' => [['multiplier' => '0.7']]]],
    ]));

    expect($result->name)->toBe('Renamed')
        ->and($result->enabled_modifiers)->toBe(['multiplier'])
        ->and($result->modifier_configs)->toHaveKey('multiplier')
        ->and($result->modifier_configs)->not->toHaveKey('factor');
});

it('updates the identity fields (name, description, strategy, base period)', function () {
    $definition = RateDefinition::factory()->create([
        'calculation_strategy' => CalculationStrategyType::Period,
    ]);

    $result = (new UpdateRateDefinition)($definition, UpdateRateDefinitionData::from([
        'name' => 'New Name',
        'description' => 'Seasonal weekly rate',
        'calculation_strategy' => 'period',
        'base_period' => 'weekly',
    ]));

    expect($result->name)->toBe('New Name')
        ->and($result->description)->toBe('Seasonal weekly rate')
        ->and($result->calculation_strategy)->toBe('period')
        ->and($result->base_period)->toBe('weekly');
});

it('deletes a rate definition', function () {
    Event::fake([AuditableEvent::class]);
    $definition = RateDefinition::factory()->create();

    (new DeleteRateDefinition)($definition);

    $this->assertDatabaseMissing('rate_definitions', ['id' => $definition->id]);
    Event::assertDispatched(AuditableEvent::class);
});

it('duplicates a definition as a non-preset copy with lineage', function () {
    $original = RateDefinition::factory()->preset()->create([
        'name' => 'Daily Rate',
        'enabled_modifiers' => ['multiplier'],
        'modifier_configs' => ['multiplier' => ['tiers' => [['multiplier' => '0.5']]]],
    ]);

    $copy = (new DuplicateRateDefinition)($original);

    expect($copy->name)->toBe('Daily Rate (Copy)')
        ->and($copy->is_preset)->toBeFalse()
        ->and($copy->preset_slug)->toBeNull()
        ->and($copy->cloned_from_id)->toBe($original->id)
        ->and($copy->modifier_configs)->toBe($original->modifier_configs);
});

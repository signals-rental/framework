<?php

namespace Database\Factories;

use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Models\RateDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RateDefinition>
 */
class RateDefinitionFactory extends Factory
{
    protected $model = RateDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Rate',
            'description' => fake()->optional()->sentence(),
            'calculation_strategy' => CalculationStrategyType::Period,
            'base_period' => BasePeriod::Daily,
            'enabled_modifiers' => [],
            'strategy_config' => [],
            'modifier_configs' => [],
            'is_preset' => false,
            'preset_slug' => null,
        ];
    }

    public function preset(): static
    {
        return $this->state(fn (): array => [
            'is_preset' => true,
            'preset_slug' => Str::slug(fake()->unique()->words(3, true)),
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn (): array => [
            'calculation_strategy' => CalculationStrategyType::Fixed,
            'base_period' => null,
        ]);
    }

    public function hybrid(): static
    {
        return $this->state(fn (): array => [
            'calculation_strategy' => CalculationStrategyType::Hybrid,
            'base_period' => BasePeriod::Daily,
            'strategy_config' => ['fixed_charge' => 20000, 'fixed_period_units' => 3],
        ]);
    }
}

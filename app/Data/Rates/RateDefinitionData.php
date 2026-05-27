<?php

namespace App\Data\Rates;

use App\Data\Concerns\FormatsTimestamps;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Models\RateDefinition;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class RateDefinitionData extends Data
{
    use FormatsTimestamps;

    /**
     * @param  list<string>  $enabled_modifiers
     * @param  array<string, mixed>  $strategy_config
     * @param  array<string, array<string, mixed>>  $modifier_configs
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public string $calculation_strategy,
        public string $calculation_strategy_name,
        public ?string $base_period,
        public ?string $base_period_name,
        public array $enabled_modifiers,
        public array $strategy_config,
        public array $modifier_configs,
        public bool $is_preset,
        public ?string $preset_slug,
        public ?int $cloned_from_id,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(RateDefinition $definition): self
    {
        /** @var CalculationStrategyType $strategy */
        $strategy = $definition->calculation_strategy;

        /** @var BasePeriod|null $basePeriod */
        $basePeriod = $definition->base_period;

        /** @var Carbon $createdAt */
        $createdAt = $definition->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $definition->updated_at;

        return new self(
            id: $definition->id,
            name: $definition->name,
            description: $definition->description,
            calculation_strategy: $strategy->value,
            calculation_strategy_name: $strategy->label(),
            base_period: $basePeriod?->value,
            base_period_name: $basePeriod?->label(),
            enabled_modifiers: $definition->enabled_modifiers ?? [],
            strategy_config: $definition->strategy_config ?? [],
            modifier_configs: $definition->modifier_configs ?? [],
            is_preset: $definition->is_preset,
            preset_slug: $definition->preset_slug,
            cloned_from_id: $definition->cloned_from_id,
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
        );
    }
}

<?php

namespace App\Data\Rates;

use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

class CreateRateDefinitionData extends Data
{
    /**
     * @param  list<string>  $enabled_modifiers
     * @param  array<string, mixed>  $strategy_config
     * @param  array<string, array<string, mixed>>  $modifier_configs
     */
    public function __construct(
        public string $name,
        public CalculationStrategyType $calculation_strategy,
        public ?BasePeriod $base_period = null,
        public ?string $description = null,
        public array $enabled_modifiers = [],
        public array $strategy_config = [],
        public array $modifier_configs = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'calculation_strategy' => ['required', new Enum(CalculationStrategyType::class)],
            'base_period' => ['sometimes', 'nullable', new Enum(BasePeriod::class)],
            'description' => ['sometimes', 'nullable', 'string'],
            'enabled_modifiers' => ['sometimes', 'array'],
            'enabled_modifiers.*' => ['string'],
            'strategy_config' => ['sometimes', 'array'],
            'modifier_configs' => ['sometimes', 'array'],
        ];
    }
}

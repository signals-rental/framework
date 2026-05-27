<?php

namespace App\Data\Rates;

use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

class UpdateRateDefinitionData extends Data
{
    /**
     * @param  list<string>|null  $enabled_modifiers
     * @param  array<string, mixed>|null  $strategy_config
     * @param  array<string, array<string, mixed>>|null  $modifier_configs
     */
    public function __construct(
        public ?string $name = null,
        public ?CalculationStrategyType $calculation_strategy = null,
        public ?BasePeriod $base_period = null,
        public ?string $description = null,
        public ?array $enabled_modifiers = null,
        public ?array $strategy_config = null,
        public ?array $modifier_configs = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'calculation_strategy' => ['sometimes', new Enum(CalculationStrategyType::class)],
            'base_period' => ['sometimes', 'nullable', new Enum(BasePeriod::class)],
            'description' => ['sometimes', 'nullable', 'string'],
            'enabled_modifiers' => ['sometimes', 'array'],
            'enabled_modifiers.*' => ['string'],
            'strategy_config' => ['sometimes', 'array'],
            'modifier_configs' => ['sometimes', 'array'],
        ];
    }
}

<?php

namespace App\Actions\Rates\Concerns;

use App\Services\RateEngine\RateEngineRegistry;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait ValidatesRateConfig
{
    /**
     * Validate a rate definition's strategy and modifier config against the
     * composed config schema, throwing a ValidationException on failure.
     *
     * @param  array<int, string>  $enabledModifiers
     * @param  array<string, mixed>  $strategyConfig
     * @param  array<string, array<string, mixed>>  $modifierConfigs
     *
     * @throws ValidationException
     */
    protected function validateRateConfig(string $strategy, array $enabledModifiers, array $strategyConfig, array $modifierConfigs): void
    {
        $rules = app(RateEngineRegistry::class)
            ->configRules($strategy, $enabledModifiers, $strategyConfig, $modifierConfigs);

        Validator::make(
            ['strategy_config' => $strategyConfig, 'modifier_configs' => $modifierConfigs],
            $rules,
        )->validate();
    }
}

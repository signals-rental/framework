<?php

namespace App\Actions\Rates\Concerns;

use App\Contracts\CalculationStrategy;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
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

    /**
     * Validate that the base period and enabled modifiers are compatible with the
     * chosen strategy. A strategy that requires a base period must be given one
     * from its allowed set, and each enabled modifier must be registered and
     * supported by the strategy. The config-schema validation alone does not
     * cover these structural constraints.
     *
     * @param  array<int, string>  $enabledModifiers
     *
     * @throws ValidationException
     */
    protected function validateStrategyCompatibility(CalculationStrategyType $strategy, ?BasePeriod $basePeriod, array $enabledModifiers): void
    {
        /** @var array<string, array<int, string>> $errors */
        $errors = [];

        if ($strategy->requiresBasePeriod()) {
            $allowed = $strategy->allowedBasePeriods();

            if ($basePeriod === null) {
                $errors['base_period'][] = "The {$strategy->label()} strategy requires a base period.";
            } elseif (! in_array($basePeriod, $allowed, true)) {
                $values = implode(', ', array_map(static fn (BasePeriod $period): string => $period->value, $allowed));
                $errors['base_period'][] = "The [{$basePeriod->value}] base period is not valid for the {$strategy->label()} strategy. Allowed: {$values}.";
            }
        }

        $registry = app(RateEngineRegistry::class);
        $registryStrategy = $registry->strategy($strategy->value);

        foreach ($enabledModifiers as $modifierId) {
            if (! $registry->hasModifier($modifierId)) {
                $errors['enabled_modifiers'][] = "Unknown modifier [{$modifierId}].";

                continue;
            }

            if (! $this->strategySupportsModifier($registryStrategy, $modifierId)) {
                $errors['enabled_modifiers'][] = "The [{$modifierId}] modifier is not supported by the {$strategy->label()} strategy.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Whether the strategy supports a given core modifier. Plugin modifiers
     * beyond the core multiplier/factor are not constrained here.
     */
    private function strategySupportsModifier(CalculationStrategy $strategy, string $modifierId): bool
    {
        return match ($modifierId) {
            'multiplier' => $strategy->supportsMultiplier(),
            'factor' => $strategy->supportsFactor(),
            default => true,
        };
    }
}

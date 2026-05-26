<?php

namespace App\Services\RateEngine\Presets;

use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;

/**
 * The framework-shipped rate definition presets. Each preset replicates one
 * Current RMS engine type so migrating users see familiar names, but presets are
 * just pre-filled, fully-editable configurations of the composable rate engine
 * (strategy + base period + modifiers).
 *
 * Current RMS's "Days Used Rate Engine" (usage strategy) is intentionally absent:
 * the usage strategy is cut from v1, so CRMS "Days Used Rate" imports map to the
 * "Daily Rate" preset as a documented fallback.
 *
 * Modifier configs ship empty — a preset enables a modifier but leaves its tier
 * or range table for the user to fill in, matching the "starting point" intent.
 */
class RatePresets
{
    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     description: string,
     *     calculation_strategy: CalculationStrategyType,
     *     base_period: BasePeriod|null,
     *     enabled_modifiers: list<string>,
     *     strategy_config: array<string, mixed>,
     *     modifier_configs: array<string, mixed>,
     * }>
     */
    public static function all(): array
    {
        return [
            self::preset('daily-multiplier-factor', 'Daily Multiplier and Factor', 'Daily rate with tiered duration multipliers and quantity factors.', CalculationStrategyType::Period, BasePeriod::Daily, ['multiplier', 'factor']),
            self::preset('daily-rate', 'Daily Rate', 'A flat charge for each day of the rental.', CalculationStrategyType::Period, BasePeriod::Daily),
            self::preset('fixed-rate-factor', 'Fixed Rate and Factor', 'A flat charge scaled by quantity factor, regardless of duration.', CalculationStrategyType::Fixed, null, ['factor']),
            self::preset('fixed-rate-subs-days', 'Fixed Rate and Subs Days', 'A fixed charge for an initial period, then a per-day charge thereafter.', CalculationStrategyType::Hybrid, BasePeriod::Daily),
            self::preset('fixed-rate', 'Fixed Rate', 'A single flat charge regardless of duration.', CalculationStrategyType::Fixed, null),
            self::preset('half-hourly-rate', 'Half Hourly Rate', 'A flat charge for each half-hour of the rental.', CalculationStrategyType::Period, BasePeriod::HalfHourly),
            self::preset('hourly-multiplier-factor', 'Hourly Multiplier and Factor', 'Hourly rate with tiered duration multipliers and quantity factors.', CalculationStrategyType::Period, BasePeriod::Hourly, ['multiplier', 'factor']),
            self::preset('hourly-rate', 'Hourly Rate', 'A flat charge for each hour of the rental.', CalculationStrategyType::Period, BasePeriod::Hourly),
            self::preset('monthly-multiplier-factor', 'Monthly Multiplier and Factor', 'Monthly rate with tiered duration multipliers and quantity factors.', CalculationStrategyType::Period, BasePeriod::Monthly, ['multiplier', 'factor']),
            self::preset('monthly-rate', 'Monthly Rate', 'A flat charge for each month of the rental.', CalculationStrategyType::Period, BasePeriod::Monthly),
            self::preset('weekly-rate', 'Weekly Rate', 'A flat charge for each week of the rental.', CalculationStrategyType::Period, BasePeriod::Weekly),
        ];
    }

    /**
     * @param  list<string>  $enabledModifiers
     * @return array{
     *     slug: string,
     *     name: string,
     *     description: string,
     *     calculation_strategy: CalculationStrategyType,
     *     base_period: BasePeriod|null,
     *     enabled_modifiers: list<string>,
     *     strategy_config: array<string, mixed>,
     *     modifier_configs: array<string, mixed>,
     * }
     */
    private static function preset(
        string $slug,
        string $name,
        string $description,
        CalculationStrategyType $strategy,
        ?BasePeriod $basePeriod,
        array $enabledModifiers = [],
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'calculation_strategy' => $strategy,
            'base_period' => $basePeriod,
            'enabled_modifiers' => $enabledModifiers,
            'strategy_config' => [],
            'modifier_configs' => [],
        ];
    }
}

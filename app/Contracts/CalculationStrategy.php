<?php

namespace App\Contracts;

use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Support\ConfigSchema\Schema;
use App\ValueObjects\CalculationContext;
use App\ValueObjects\RateBreakdown;

/**
 * A calculation strategy converts elapsed rental time into a base
 * {@see RateBreakdown}, before any modifiers (multipliers, factors) are applied.
 *
 * Strategies are deterministic, side-effect-free functions of their
 * {@see CalculationContext}: they perform no database access or external IO,
 * which makes the whole pipeline unit-testable. The framework ships the
 * `period`, `fixed`, and `hybrid` strategies; plugins register further
 * strategies through the rate engine registry.
 */
interface CalculationStrategy
{
    /**
     * Stable identifier, matching a {@see CalculationStrategyType}
     * value: `period`, `fixed`, or `hybrid`.
     */
    public function identifier(): string;

    /**
     * Human-readable name shown when building a rate definition.
     */
    public function label(): string;

    /**
     * Base periods this strategy permits. An empty array means the strategy
     * does not use a base period (e.g. fixed charges).
     *
     * @return array<int, BasePeriod>
     */
    public function allowedBasePeriods(): array;

    /**
     * Whether the multiplier modifier may be enabled on a definition using
     * this strategy.
     */
    public function supportsMultiplier(): bool;

    /**
     * Whether the factor modifier may be enabled on a definition using this
     * strategy.
     */
    public function supportsFactor(): bool;

    /**
     * The declarative schema for this strategy's `strategy_config`, used to
     * validate and render its configuration. An empty schema means the strategy
     * has no configurable options (e.g. fixed charges).
     */
    public function configSchema(): Schema;

    /**
     * Produce the base breakdown for the given context, before any modifiers.
     */
    public function calculate(CalculationContext $context): RateBreakdown;
}

<?php

namespace App\Contracts;

use App\ValueObjects\CalculationContext;
use App\ValueObjects\RateBreakdown;

/**
 * A rate modifier transforms the base {@see RateBreakdown} produced by a
 * {@see CalculationStrategy}, adjusting the per-unit subtotal and recording its
 * effect in the breakdown's audit trail.
 *
 * Like strategies, modifiers are deterministic, side-effect-free functions: they
 * read a plain config array and the {@see CalculationContext} and return a new
 * breakdown, performing no database access or external IO. The framework ships
 * the `multiplier` and `factor` modifiers; plugins register further modifiers
 * through the rate engine registry. Modifiers run in ascending {@see self::priority()}
 * order, so `multiplier` (which rewrites per-period line items) runs before
 * `factor` (which scales the resulting subtotal).
 */
interface RateModifier
{
    /**
     * Stable identifier (e.g. `multiplier`, `factor`). Used as the config key
     * under which this modifier's configuration is stored on a rate definition.
     */
    public function identifier(): string;

    /**
     * Human-readable name shown when building a rate definition.
     */
    public function label(): string;

    /**
     * Ordering weight. Modifiers are applied in ascending order; lower runs
     * first. Core modifiers reserve 100 (multiplier) and 200 (factor); plugins
     * should use values above 200 to run after the core modifiers.
     */
    public function priority(): int;

    /**
     * Apply this modifier to the breakdown, returning a new instance. The
     * breakdown is never mutated in place.
     *
     * @param  array<string, mixed>  $config  Validated configuration for this modifier
     */
    public function apply(RateBreakdown $breakdown, array $config, CalculationContext $context): RateBreakdown;
}

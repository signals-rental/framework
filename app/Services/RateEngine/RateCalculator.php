<?php

namespace App\Services\RateEngine;

use App\ValueObjects\CalculationContext;
use App\ValueObjects\RateBreakdown;

/**
 * Orchestrates the rate calculation pipeline: resolve the calculation strategy
 * to produce a base {@see RateBreakdown}, then apply the enabled modifiers in
 * registry priority order to arrive at the final breakdown.
 *
 * The calculator is a pure function of its inputs — it performs no database
 * access or external IO. Persistence-aware callers (a `RateDefinition` model in
 * a later milestone) read the strategy identifier, enabled modifiers and config
 * off the definition and pass them here.
 */
class RateCalculator
{
    public function __construct(private readonly RateEngineRegistry $registry) {}

    /**
     * Calculate the breakdown for a context under a given strategy and set of
     * enabled modifiers.
     *
     * @param  string  $strategy  Strategy identifier (e.g. `period`, `hybrid`)
     * @param  array<int, string>  $enabledModifiers  Identifiers of the modifiers to run
     * @param  array<string, array<string, mixed>>  $modifierConfigs  Per-modifier config keyed by identifier
     */
    public function calculate(
        CalculationContext $context,
        string $strategy,
        array $enabledModifiers = [],
        array $modifierConfigs = [],
    ): RateBreakdown {
        $breakdown = $this->registry->strategy($strategy)->calculate($context);

        foreach ($this->registry->modifiers() as $modifier) {
            if (! in_array($modifier->identifier(), $enabledModifiers, true)) {
                continue;
            }

            $config = $modifierConfigs[$modifier->identifier()] ?? [];

            $breakdown = $modifier->apply($breakdown, $config, $context);
        }

        return $breakdown;
    }
}

<?php

namespace App\Services\RateEngine;

use App\Contracts\CalculationStrategy;
use App\Contracts\RateModifier;
use App\Support\ConfigSchema\Section;
use InvalidArgumentException;

/**
 * Central registry of the calculation strategies and rate modifiers available
 * to the rate engine. The framework registers its core strategies (`period`,
 * `fixed`, `hybrid`) and modifiers (`multiplier`, `factor`) on boot; plugins
 * register further ones through the same API.
 *
 * Modifiers are returned in ascending {@see RateModifier::priority()} order so
 * the {@see RateCalculator} applies them deterministically (multiplier before
 * factor before plugin modifiers).
 */
class RateEngineRegistry
{
    /** @var array<string, CalculationStrategy> */
    private array $strategies = [];

    /** @var array<string, RateModifier> */
    private array $modifiers = [];

    public function registerStrategy(CalculationStrategy $strategy): void
    {
        $this->strategies[$strategy->identifier()] = $strategy;
    }

    public function hasStrategy(string $identifier): bool
    {
        return isset($this->strategies[$identifier]);
    }

    public function strategy(string $identifier): CalculationStrategy
    {
        return $this->strategies[$identifier]
            ?? throw new InvalidArgumentException("No calculation strategy registered for [{$identifier}].");
    }

    /**
     * All registered strategies keyed by identifier.
     *
     * @return array<string, CalculationStrategy>
     */
    public function strategies(): array
    {
        return $this->strategies;
    }

    public function registerModifier(RateModifier $modifier): void
    {
        $this->modifiers[$modifier->identifier()] = $modifier;
    }

    public function hasModifier(string $identifier): bool
    {
        return isset($this->modifiers[$identifier]);
    }

    public function modifier(string $identifier): RateModifier
    {
        return $this->modifiers[$identifier]
            ?? throw new InvalidArgumentException("No rate modifier registered for [{$identifier}].");
    }

    /**
     * All registered modifiers, ordered by ascending priority.
     *
     * @return array<int, RateModifier>
     */
    public function modifiers(): array
    {
        $modifiers = array_values($this->modifiers);

        usort($modifiers, static fn (RateModifier $a, RateModifier $b): int => $a->priority() <=> $b->priority());

        return $modifiers;
    }

    /**
     * Compose the form sections for a rate definition: the strategy's "Options"
     * section (omitted when the strategy has no config) followed by one section
     * per enabled modifier, in registry priority order.
     *
     * @param  array<int, string>  $enabledModifierIds
     * @return array<int, Section>
     */
    public function composeSections(string $strategyId, array $enabledModifierIds): array
    {
        $sections = [];

        $strategySchema = $this->strategy($strategyId)->configSchema();

        if (! $strategySchema->isEmpty()) {
            $sections[] = new Section('options', 'Options', $strategySchema);
        }

        foreach ($this->modifiers() as $modifier) {
            if (in_array($modifier->identifier(), $enabledModifierIds, true)) {
                $sections[] = new Section($modifier->identifier(), $modifier->label(), $modifier->configSchema());
            }
        }

        return $sections;
    }

    /**
     * Sanitise a strategy's config against its schema (cast visible values, drop
     * hidden ones).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function sanitiseStrategyConfig(string $strategyId, array $config): array
    {
        return $this->strategy($strategyId)->configSchema()->sanitise($config);
    }

    /**
     * Sanitise modifier configs: keep only enabled modifiers, sanitising each
     * against its schema, so disabled modifiers' config never persists.
     *
     * @param  array<int, string>  $enabledModifierIds
     * @param  array<string, array<string, mixed>>  $configs
     * @return array<string, array<string, mixed>>
     */
    public function sanitiseModifierConfigs(array $enabledModifierIds, array $configs): array
    {
        $sanitised = [];

        foreach ($this->modifiers() as $modifier) {
            $id = $modifier->identifier();

            if (in_array($id, $enabledModifierIds, true)) {
                $sanitised[$id] = $modifier->configSchema()->sanitise($configs[$id] ?? []);
            }
        }

        return $sanitised;
    }
}

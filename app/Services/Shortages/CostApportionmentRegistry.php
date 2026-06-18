<?php

namespace App\Services\Shortages;

use App\Contracts\CostApportionmentStrategyContract;
use InvalidArgumentException;

/**
 * Registry of cost-apportionment strategies (shortage-resolution-sub-hires.md
 * §6.2). STUB for later sub-hire / virtual-stock work (Phase 4).
 *
 * Mirrors the other Signals registries so virtual stock and plugins can register
 * real strategies through the same interface. Ships only the no-op
 * {@see NullCostApportionmentStrategy} for now.
 */
class CostApportionmentRegistry
{
    /** @var array<string, CostApportionmentStrategyContract> */
    private array $strategies = [];

    public function register(CostApportionmentStrategyContract $strategy): void
    {
        $this->strategies[$strategy->key()] = $strategy;
    }

    /**
     * @throws InvalidArgumentException when the key is not registered
     */
    public function get(string $key): CostApportionmentStrategyContract
    {
        return $this->strategies[$key]
            ?? throw new InvalidArgumentException("Unknown cost-apportionment strategy: {$key}");
    }

    public function has(string $key): bool
    {
        return isset($this->strategies[$key]);
    }

    /**
     * @return array<string, CostApportionmentStrategyContract>
     */
    public function all(): array
    {
        return $this->strategies;
    }
}

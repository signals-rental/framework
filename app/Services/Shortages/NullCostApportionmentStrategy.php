<?php

namespace App\Services\Shortages;

use App\Contracts\CostApportionmentStrategyContract;

/**
 * Default no-op cost-apportionment strategy — the stub the
 * {@see CostApportionmentRegistry} ships with until virtual stock (Phase 4)
 * provides the real strategies. Allocates nothing, so registering it keeps the
 * extension point live without affecting any cost figures.
 */
class NullCostApportionmentStrategy implements CostApportionmentStrategyContract
{
    public function key(): string
    {
        return 'none';
    }

    public function name(): string
    {
        return 'No apportionment';
    }

    public function requiresManualInput(): bool
    {
        return false;
    }

    /**
     * @return array<int, int>
     */
    public function calculate(mixed $intake): array
    {
        return [];
    }
}

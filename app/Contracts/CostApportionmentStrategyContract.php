<?php

namespace App\Contracts;

/**
 * Cost-apportionment strategy contract (shortage-resolution-sub-hires.md §6.2).
 *
 * STUB for later sub-hire / virtual-stock work (Phase 4). Apportionment
 * distributes the cost of virtual (sub-hired/purchased) stock across the
 * opportunities that use it. Virtual stock intakes and `opportunity_costs`
 * integration are out of scope for this milestone, so the contract is declared
 * (with a registry and a default no-op strategy) purely so the extension point
 * exists from the outset. The concrete strategies (primary_job, even_split,
 * proportional_quantity, proportional_quantity_duration, manual) land with
 * virtual stock.
 *
 * `calculate()` receives an intake-like source and returns per-opportunity cost
 * allocations. It is typed loosely (mixed source) until the VirtualStockIntake
 * model exists, to avoid introducing Phase-4 types prematurely.
 */
interface CostApportionmentStrategyContract
{
    /**
     * Unique strategy identifier (e.g. `primary_job`, `even_split`).
     */
    public function key(): string;

    /**
     * Human-readable name for display.
     */
    public function name(): string;

    /**
     * Whether this strategy requires explicit per-opportunity input (only the
     * `manual` strategy does).
     */
    public function requiresManualInput(): bool;

    /**
     * Compute per-opportunity cost allocations for the given intake source.
     *
     * @param  mixed  $intake  the virtual-stock intake (typed when Phase 4 lands)
     * @return array<int, int> map of opportunity_id => allocated cost in minor units
     */
    public function calculate(mixed $intake): array;
}

<?php

namespace App\Services\Shortages;

use App\Contracts\ShortageResolverContract;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\Shortage;

/**
 * Synchronous auto-resolution loop (shortage-resolution-sub-hires.md §7.5).
 *
 * When a store has `shortage_auto_resolve_enabled`, this runs BEFORE the
 * {@see ShortageConfirmationGate} evaluates so the gate sees only the residual
 * shortage. For each shortage it iterates the store's ordered
 * `shortage_preferred_resolvers` (falling back to every registered resolver in
 * priority order when none is configured), and for each resolver that is
 * {@see ShortageResolverContract::isAutoExecutable()} it executes the first
 * option flagged `auto_executable`. After each execution it re-detects the item
 * so `remaining_shortfall` reflects the resolution records just written, stopping
 * once the shortage is fully covered.
 *
 * Config-driven by design: it never references resolver keys directly — the
 * order comes from store config, the applicability from each resolver, and the
 * auto-execution decision from the option flag plus the resolver's own
 * `isAutoExecutable()`. A store with auto-resolve off is a no-op.
 */
class ShortageAutoResolver
{
    public function __construct(
        private readonly ShortageDetector $detector,
        private readonly ShortageResolverRegistry $registry,
    ) {}

    /**
     * Run auto-resolution across every shortage on an opportunity. Returns the
     * number of resolution options auto-executed (0 when auto-resolve is off or
     * nothing was auto-executable).
     */
    public function resolve(Opportunity $opportunity): int
    {
        $store = $opportunity->store;

        if ($store === null || ! $store->autoResolvesShortages()) {
            return 0;
        }

        $resolverOrder = $this->resolverOrder($store->preferredResolvers());

        if ($resolverOrder === []) {
            return 0;
        }

        $opportunity->loadMissing('items');

        $executed = 0;

        foreach ($opportunity->items as $item) {
            $executed += $this->resolveItem($item, $opportunity, $resolverOrder);
        }

        return $executed;
    }

    /**
     * Auto-resolve a single line item: walk the resolver order, executing the
     * first auto-executable option from each, re-detecting between executions so
     * coverage compounds. Stops as soon as the item is fully serviceable.
     *
     * @param  list<ShortageResolverContract>  $resolverOrder
     */
    private function resolveItem(OpportunityItem $item, Opportunity $opportunity, array $resolverOrder): int
    {
        $executed = 0;

        $shortage = $this->detector->forItem($item, $opportunity);

        if ($shortage === null || ! $shortage->isUnresolved()) {
            return 0;
        }

        foreach ($resolverOrder as $resolver) {
            if (! $resolver->isAutoExecutable() || ! $resolver->canResolve($shortage)) {
                continue;
            }

            $option = $this->autoExecutableOption($resolver, $shortage);

            if ($option === null) {
                continue;
            }

            $resolver->apply($shortage, $option);
            $executed++;

            // Re-detect so remaining_shortfall reflects the resolution just
            // written; a later resolver only acts on what is still short.
            $shortage = $this->detector->forItem($item->refresh(), $opportunity);

            if ($shortage === null || ! $shortage->isUnresolved()) {
                break;
            }
        }

        return $executed;
    }

    /**
     * The first option a resolver offers for the shortage that is flagged
     * auto-executable, or null when it offers none.
     */
    private function autoExecutableOption(ShortageResolverContract $resolver, Shortage $shortage): ?ResolutionOption
    {
        foreach ($resolver->getOptions($shortage) as $option) {
            if ($option->autoExecutable) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Resolve the ordered resolver instances to run. A configured
     * `shortage_preferred_resolvers` list is honoured in order, skipping unknown
     * keys; an empty list falls back to every registered resolver in priority
     * order.
     *
     * @param  list<string>  $preferredKeys
     * @return list<ShortageResolverContract>
     */
    private function resolverOrder(array $preferredKeys): array
    {
        if ($preferredKeys === []) {
            return $this->registry->all();
        }

        $resolvers = [];

        foreach ($preferredKeys as $key) {
            if ($this->registry->has($key)) {
                $resolvers[] = $this->registry->resolve($key);
            }
        }

        return $resolvers;
    }
}

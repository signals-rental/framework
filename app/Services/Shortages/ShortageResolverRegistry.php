<?php

namespace App\Services\Shortages;

use App\Contracts\ShortageResolverContract;
use App\Services\DemandSourceRegistry;
use App\ValueObjects\Shortage;
use InvalidArgumentException;

/**
 * Plugin-extensible registry of shortage resolvers
 * (shortage-resolution-sub-hires.md §3.1).
 *
 * Follows the same shape as {@see DemandSourceRegistry} and the
 * other Signals registries: core registers the built-in non-PO resolvers in the
 * service provider; plugins register their own through the same interface so the
 * shortage panel and confirmation gate gather options from every resolver without
 * any core change.
 *
 * Resolvers are looked up by key and instantiated through the container via
 * {@see resolve()}, keeping them mockable and dependency-injectable.
 */
class ShortageResolverRegistry
{
    /** @var array<string, ShortageResolverDefinition> */
    private array $resolvers = [];

    public function register(ShortageResolverDefinition $definition): void
    {
        $this->resolvers[$definition->key] = $definition;
    }

    /**
     * @throws InvalidArgumentException when the key is not registered
     */
    public function get(string $key): ShortageResolverDefinition
    {
        return $this->resolvers[$key]
            ?? throw new InvalidArgumentException("Unknown shortage resolver: {$key}");
    }

    /**
     * @return array<string, ShortageResolverDefinition>
     */
    public function definitions(): array
    {
        return $this->resolvers;
    }

    public function has(string $key): bool
    {
        return isset($this->resolvers[$key]);
    }

    /**
     * Resolve a single resolver instance from the container.
     *
     * @throws InvalidArgumentException when the key is not registered
     */
    public function resolve(string $key): ShortageResolverContract
    {
        return app($this->get($key)->resolverClass);
    }

    /**
     * Every registered resolver instance, ordered by display priority (lower
     * first). The order the shortage panel renders resolver sections in.
     *
     * @return list<ShortageResolverContract>
     */
    public function all(): array
    {
        $instances = array_map(
            fn (ShortageResolverDefinition $definition): ShortageResolverContract => app($definition->resolverClass),
            array_values($this->resolvers),
        );

        usort(
            $instances,
            static fn (ShortageResolverContract $a, ShortageResolverContract $b): int => $a->priority() <=> $b->priority(),
        );

        return $instances;
    }

    /**
     * The resolvers applicable to a specific shortage ({@see ShortageResolverContract::canResolve()}),
     * in priority order — what the shortage panel offers for that line.
     *
     * @return list<ShortageResolverContract>
     */
    public function applicableTo(Shortage $shortage): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (ShortageResolverContract $resolver): bool => $resolver->canResolve($shortage),
        ));
    }
}

<?php

namespace App\Services;

use App\Contracts\DemandResolverContract;
use InvalidArgumentException;

/**
 * Plugin-extensible registry of availability demand sources.
 *
 * Follows the same shape as {@see NotificationRegistry} and the other Signals
 * registries: core registers the built-in sources (opportunity item, …) in the
 * service provider; plugins register their own through the same interface so the
 * engine needs no changes to recognise new demand types.
 *
 * Resolvers are looked up by source type and instantiated through the container
 * via {@see resolve()}, keeping them mockable and dependency-injectable.
 */
class DemandSourceRegistry
{
    /** @var array<string, DemandSourceDefinition> */
    private array $sources = [];

    public function register(DemandSourceDefinition $definition): void
    {
        $this->sources[$definition->type] = $definition;
    }

    /**
     * @throws InvalidArgumentException when the type is not registered
     */
    public function get(string $type): DemandSourceDefinition
    {
        return $this->sources[$type]
            ?? throw new InvalidArgumentException("Unknown demand source: {$type}");
    }

    /**
     * @return array<string, DemandSourceDefinition>
     */
    public function all(): array
    {
        return $this->sources;
    }

    public function has(string $type): bool
    {
        return isset($this->sources[$type]);
    }

    /**
     * Resolve the demand resolver for the given source type from the container.
     *
     * @throws InvalidArgumentException when the type is not registered
     */
    public function resolve(string $type): DemandResolverContract
    {
        return app($this->get($type)->resolverClass);
    }
}

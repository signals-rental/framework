<?php

namespace App\Services\Shortages;

use App\Contracts\ShortageResolverContract;
use App\Services\DemandSourceDefinition;

/**
 * Declarative definition of a shortage resolver registered in the
 * {@see ShortageResolverRegistry}. Mirrors {@see DemandSourceDefinition}:
 * a small readonly value object pairing the resolver key with the class to
 * resolve from the container.
 */
final readonly class ShortageResolverDefinition
{
    /**
     * @param  class-string<ShortageResolverContract>  $resolverClass
     */
    public function __construct(
        public string $key,
        public string $resolverClass,
    ) {}
}

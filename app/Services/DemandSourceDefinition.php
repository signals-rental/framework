<?php

namespace App\Services;

use App\Contracts\DemandResolverContract;

/**
 * Describes one registered demand source for the availability engine.
 *
 * Carries the source's stable type identifier, its UI presentation (display
 * name, colour, icon for timeline/breakdown views), and the FQN of the
 * {@see DemandResolverContract} implementation that translates the source's
 * entities into demand rows.
 */
class DemandSourceDefinition
{
    /**
     * @param  class-string<DemandResolverContract>  $resolverClass
     */
    public function __construct(
        public readonly string $type,
        public readonly string $displayName,
        public readonly string $resolverClass,
        public readonly string $colour,
        public readonly string $icon,
    ) {}
}

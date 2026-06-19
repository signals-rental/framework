<?php

namespace App\Enums;

/**
 * How a containerable product's serialised instances interact with the
 * availability engine and the container lifecycle
 * (serialised-containers.md §"Container Availability Modes").
 *
 *  - **Transport** (default) — contents remain individually available while
 *    packed; packing creates NO availability demand. The container is a
 *    warehouse-organisation convenience that dissolves on dispatch. (Dispatch /
 *    dissolve is Phase 4; for availability M5-3b this mode simply means "no
 *    container demands".)
 *  - **Kit** — contents are removed from individual availability while packed
 *    (a `source_type = 'container'` demand per item, sentinel-dated). The kit
 *    container product itself is the bookable entity. Persists across dispatch.
 *  - **Hybrid** — fixed-binding slots behave like kit mode (container demands);
 *    pool-binding slots behave like transport (no container demands, drawn from
 *    general stock per dispatch).
 */
enum ContainerAvailabilityMode: string
{
    case Kit = 'kit';
    case Transport = 'transport';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Kit => 'Kit',
            self::Transport => 'Transport',
            self::Hybrid => 'Hybrid',
        };
    }

    /**
     * Whether packing an item under this mode creates a container demand that
     * removes the item from individual availability.
     *
     * Kit removes everything; transport removes nothing; hybrid removes only the
     * fixed-binding slots (decided per-component, not by the mode alone), so it
     * reports false here and the resolver branches on the component binding.
     */
    public function holdsContentsIndefinitely(): bool
    {
        return $this === self::Kit;
    }

    /**
     * Whether this mode produces a bookable kit whose availability is the
     * serialised availability of the container housing (kit + hybrid), as opposed
     * to transport mode where contents stay individually bookable.
     */
    public function isBookableKit(): bool
    {
        return $this === self::Kit || $this === self::Hybrid;
    }
}

<?php

namespace App\Enums;

/**
 * The packing scan mode for a single container instance
 * (serialised-containers.md §"Scan Modes").
 *
 *  - **Strict** — the product template is a mandatory packing checklist; the
 *    container cannot be sealed until every slot is filled. Default for
 *    product-backed containers with a template.
 *  - **Free** — the template is ignored during packing; any eligible item may be
 *    scanned in and the container sealed at will. Temporary containers always use
 *    free mode.
 *
 * Set per-container instance, not per-product.
 */
enum ContainerScanMode: string
{
    case Strict = 'strict';
    case Free = 'free';

    public function label(): string
    {
        return match ($this) {
            self::Strict => 'Strict',
            self::Free => 'Free',
        };
    }
}

<?php

namespace App\Contracts\Availability;

use App\Enums\AvailabilityResolution;

/**
 * Resolves the active availability resolution for the current context.
 *
 * Availability code MUST resolve the resolution through this provider rather
 * than reading `settings('availability.resolution')` directly. The open-source
 * default reads the system setting; the commercial Cloud package can rebind it
 * to a hosting- or tenant-enforced implementation without the core knowing
 * anything about tenancy.
 */
interface AvailabilityResolutionProvider
{
    /**
     * The resolution governing demand windows and availability snapshots.
     */
    public function resolve(): AvailabilityResolution;
}

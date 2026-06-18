<?php

namespace App\Contracts\Availability;

/**
 * Reports whether any availability data (demands, snapshots) currently exists.
 *
 * Used by the availability resolution immutability guard: the resolution setting
 * may only be changed while no availability data exists, since an existing
 * dataset is bucketed to the current resolution and cannot be re-bucketed in
 * place.
 *
 * Resolved through the container so the check is mockable in tests and so the
 * commercial Cloud package can rebind it to a tenant-aware implementation
 * without the open-source core needing any tenancy awareness.
 */
interface AvailabilityDataPresence
{
    /**
     * Whether any availability data exists.
     */
    public function exists(): bool;
}

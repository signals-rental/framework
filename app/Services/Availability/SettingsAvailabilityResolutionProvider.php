<?php

namespace App\Services\Availability;

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;

/**
 * Default availability resolution provider.
 *
 * Reads the resolution from the `availability.resolution` system setting,
 * falling back to {@see AvailabilityResolution::Daily} when unset or invalid.
 * The Cloud package can rebind {@see AvailabilityResolutionProvider} to enforce
 * a hosting-level resolution without the core knowing about tenancy.
 */
class SettingsAvailabilityResolutionProvider implements AvailabilityResolutionProvider
{
    public function resolve(): AvailabilityResolution
    {
        $value = settings('availability.resolution', AvailabilityResolution::Daily->value);

        return is_string($value)
            ? (AvailabilityResolution::tryFrom($value) ?? AvailabilityResolution::Daily)
            : AvailabilityResolution::Daily;
    }
}

<?php

namespace App\Services\Availability;

use App\Contracts\Availability\AvailabilityDataPresence;
use App\Models\Demand;
use Illuminate\Support\Facades\Schema;

/**
 * Default, tenant-ignorant availability-data presence check.
 *
 * Availability data is considered present when the `demands` table exists and
 * holds at least one row. In M1 the `demands` table does not yet exist, so this
 * returns `false` — meaning the resolution setting is freely changeable until
 * the availability engine lands and produces data. Once the table exists and is
 * populated (M2+), the resolution becomes immutable.
 */
class DatabaseAvailabilityDataPresence implements AvailabilityDataPresence
{
    public function exists(): bool
    {
        if (! Schema::hasTable('demands')) {
            return false;
        }

        return Demand::query()->exists();
    }
}

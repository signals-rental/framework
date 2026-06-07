<?php

namespace App\Data\Concerns;

use Illuminate\Support\Carbon;

trait FormatsTimestamps
{
    /**
     * Format a Carbon timestamp in RMS format (UTC with Z suffix and milliseconds).
     */
    protected static function formatTimestamp(\DateTimeInterface $timestamp): string
    {
        return Carbon::instance($timestamp)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}

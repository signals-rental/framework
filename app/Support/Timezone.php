<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class Timezone
{
    /**
     * Get the current timezone for display, resolving from:
     * 1. Authenticated user's timezone
     * 2. Company settings timezone
     * 3. UTC fallback
     */
    public function current(): string
    {
        $user = auth()->user();

        if ($user && $user->timezone) {
            return $user->timezone;
        }

        return settings('company.timezone') ?? 'UTC';
    }

    /**
     * Convert a UTC datetime to the local timezone.
     */
    public function toLocal(DateTimeInterface|string $datetime): CarbonImmutable
    {
        $carbon = $datetime instanceof DateTimeInterface
            ? CarbonImmutable::instance($datetime)
            : CarbonImmutable::parse($datetime, 'UTC');

        return $carbon->setTimezone($this->current());
    }

    /**
     * Convert a local datetime to UTC.
     */
    public function toUtc(DateTimeInterface|string $datetime): CarbonImmutable
    {
        $carbon = $datetime instanceof DateTimeInterface
            ? CarbonImmutable::instance($datetime)
            : CarbonImmutable::parse($datetime, $this->current());

        return $carbon->setTimezone('UTC');
    }

    /**
     * Parse user input (assumed to be in local timezone) to UTC Carbon.
     */
    public function parseUserInput(string $input, ?string $format = null): Carbon
    {
        if ($format) {
            $local = Carbon::createFromFormat($format, $input, $this->current());
        } else {
            $local = Carbon::parse($input, $this->current());
        }

        return $local->setTimezone('UTC');
    }
}

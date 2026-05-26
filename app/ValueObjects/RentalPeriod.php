<?php

namespace App\ValueObjects;

use App\Enums\BasePeriod;
use App\Enums\DayType;
use Illuminate\Support\Carbon;

/**
 * Immutable rental window that knows how to convert itself into a count of
 * chargeable units for a given base period and set of time options.
 *
 * This is the time-interpretation heart of the rate engine: leeway grace
 * periods, last-day cut-offs, day-type (clock vs. business hours) and
 * configurable rental-week length all feed into {@see self::chargeableUnits()}.
 */
class RentalPeriod
{
    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
    ) {}

    /**
     * Convert the rental window into a whole number of chargeable units.
     *
     * Recognised (all optional) keys in $timeOptions:
     *  - day_type (DayType): clock (default) or business hours
     *  - business_hours_start (string "HH:MM"): start of the business window
     *  - business_hours_end (string "HH:MM"): end of the business window
     *  - rental_days_per_week (int, default 7): days that make up one weekly unit
     *  - leeway_minutes (int, default 0): grace period before the next unit accrues
     *  - first_day_cutoff (string "HH:MM"|null): when the collection time-of-day is
     *    later than this cut-off, the first day is charged in full (the effective
     *    start floors to the start of that day) so a late pickup is not pro-rated
     *  - last_day_cutoff (string "HH:MM"|null): when the return time-of-day is earlier
     *    than this cut-off, the final partial day is dropped
     *
     * @param  array<string, mixed>  $timeOptions
     */
    public function chargeableUnits(BasePeriod $period, array $timeOptions): int
    {
        $effStart = $this->start->copy();
        $effEnd = $this->end->copy();

        // Step 2: drop the final partial day when returned before the last-day cut-off.
        $lastDayCutoff = $this->parseTime($timeOptions['last_day_cutoff'] ?? null);
        if ($lastDayCutoff !== null && $this->minutesIntoDay($effEnd) < $lastDayCutoff) {
            $effEnd = $effEnd->copy()->startOfDay();
        }

        // Step 3: when collection is after the first-day cut-off, charge the first
        // day in full by flooring the effective start to the beginning of that day,
        // so a late pickup never shrinks the first day's charge.
        $firstDayCutoff = $this->parseTime($timeOptions['first_day_cutoff'] ?? null);
        if ($firstDayCutoff !== null && $this->minutesIntoDay($effStart) > $firstDayCutoff) {
            $effStart = $effStart->copy()->startOfDay();
        }

        // Step 4: measure the chargeable duration in minutes.
        $dayType = $timeOptions['day_type'] ?? DayType::Clock;
        if ($dayType === DayType::Business) {
            $durationMinutes = $this->businessHoursMinutes(
                $effStart,
                $effEnd,
                $this->parseTime($timeOptions['business_hours_start'] ?? null) ?? 0,
                $this->parseTime($timeOptions['business_hours_end'] ?? null) ?? 1440,
            );
        } else {
            $durationMinutes = (int) abs($effEnd->diffInMinutes($effStart));
        }

        // Step 5: resolve the length of one chargeable unit in minutes.
        // Floor the rental week at one day so a malformed 0 cannot divide by zero.
        $rentalDaysPerWeek = max(1, (int) ($timeOptions['rental_days_per_week'] ?? 7));
        $periodMinutes = $period === BasePeriod::Weekly
            ? $rentalDaysPerWeek * 1440
            : $period->minutes();

        // Steps 6-7: apply leeway, then round up to whole units with a floor of 1.
        $leewayMinutes = (int) ($timeOptions['leeway_minutes'] ?? 0);
        $raw = $durationMinutes - $leewayMinutes;

        return max(1, (int) ceil($raw / $periodMinutes));
    }

    /**
     * Count the minutes that fall within the business window on each calendar day
     * spanned by the rental, clipping partial first and last days to the window.
     */
    private function businessHoursMinutes(Carbon $start, Carbon $end, int $windowStart, int $windowEnd): int
    {
        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $minutes = 0;
        $cursor = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $dayWindowStart = $cursor->copy()->addMinutes($windowStart);
            $dayWindowEnd = $cursor->copy()->addMinutes($windowEnd);

            $overlapStart = $start->greaterThan($dayWindowStart) ? $start : $dayWindowStart;
            $overlapEnd = $end->lessThan($dayWindowEnd) ? $end : $dayWindowEnd;

            if ($overlapEnd->greaterThan($overlapStart)) {
                $minutes += (int) abs($overlapEnd->diffInMinutes($overlapStart));
            }

            $cursor = $cursor->addDay();
        }

        return $minutes;
    }

    /**
     * Parse an "HH:MM" string into minutes-since-midnight, or null when absent.
     */
    private function parseTime(?string $time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }

        [$hours, $minutes] = array_pad(explode(':', $time, 2), 2, '0');

        return ((int) $hours * 60) + (int) $minutes;
    }

    /**
     * Minutes elapsed since midnight for the given moment.
     */
    private function minutesIntoDay(Carbon $moment): int
    {
        return ($moment->hour * 60) + $moment->minute;
    }
}

<?php

namespace App\Support\Calendar;

use Carbon\CarbonInterface;

/**
 * Single source of truth for the calendar "all-day" rule.
 *
 * Every surface that needs to decide whether an activity is all-day — the
 * CalendarEventData DTO consumed by the web grids and the IcsFeedBuilder that
 * emits the ICS feed — funnels through this detector so the answer is identical
 * everywhere.
 *
 * Canonical rule: an event is all-day when its start AND its end both fall on a
 * (company-timezone) local midnight and the end is strictly after the start.
 * That definition naturally covers multi-day spans (e.g. a three-day
 * midnight-to-midnight block). Two long-standing conveniences are also honoured:
 * a null end (an open-ended 00:00 marker) and an inclusive 23:59 end are both
 * treated as all-day.
 *
 * IMPORTANT: the supplied Carbon instances must already be expressed in the
 * company timezone. Callers operating on raw UTC timestamps must convert first
 * (via the Timezone helper's toLocal()); detecting against UTC would resolve the
 * wrong local day for non-UTC companies.
 */
class AllDayDetector
{
    /**
     * Determine whether a start/end pair represents an all-day event.
     *
     * @param  CarbonInterface|null  $localStart  Start instant in the company timezone.
     * @param  CarbonInterface|null  $localEnd  End instant in the company timezone (null = open-ended).
     */
    public static function isAllDay(?CarbonInterface $localStart, ?CarbonInterface $localEnd): bool
    {
        if ($localStart === null) {
            return false;
        }

        if (! self::isMidnight($localStart)) {
            return false;
        }

        // An open-ended event pinned to local midnight reads as all-day.
        if ($localEnd === null) {
            return true;
        }

        // Inclusive end-of-day marker (legacy convenience).
        if ($localEnd->format('H:i') === '23:59') {
            return true;
        }

        // Canonical rule: both endpoints on local midnight, end strictly later.
        return self::isMidnight($localEnd)
            && $localEnd->greaterThan($localStart);
    }

    /**
     * Resolve the RFC 5545 *exclusive* end DATE for an all-day event.
     *
     * iCalendar all-day spans are half-open: DTEND is the day *after* the last
     * covered day. The three all-day shapes map to distinct exclusive ends so a
     * multi-day event covers its real range instead of collapsing to one day:
     *  - null end       → start + 1 day (single open-ended day).
     *  - 23:59 end      → the inclusive last day's following midnight (end + 1 day).
     *  - midnight end   → already the exclusive boundary, used as-is.
     *
     * The returned instant carries the same timezone as the inputs, so callers
     * should format its LOCAL date.
     *
     * @param  CarbonInterface  $localStart  Start instant in the company timezone.
     * @param  CarbonInterface|null  $localEnd  End instant in the company timezone.
     */
    public static function exclusiveEndDate(CarbonInterface $localStart, ?CarbonInterface $localEnd): CarbonInterface
    {
        if ($localEnd === null) {
            return $localStart->copy()->addDay()->startOfDay();
        }

        if (self::isMidnight($localEnd)) {
            return $localEnd->copy()->startOfDay();
        }

        return $localEnd->copy()->addDay()->startOfDay();
    }

    /**
     * Whether the instant falls exactly on a local midnight (00:00:00).
     */
    private static function isMidnight(CarbonInterface $instant): bool
    {
        return $instant->format('H:i:s') === '00:00:00';
    }
}

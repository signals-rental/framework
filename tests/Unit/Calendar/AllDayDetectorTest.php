<?php

use App\Support\Calendar\AllDayDetector;
use Carbon\CarbonImmutable;

/**
 * Pure unit coverage for the shared all-day rule. Inputs are treated as already
 * being in the company timezone (the callers convert before delegating here), so
 * these cases use a fixed offset zone and never touch the database.
 */
function localInstant(string $value, string $tz = 'Asia/Singapore'): CarbonImmutable
{
    return CarbonImmutable::parse($value, $tz);
}

describe('AllDayDetector::isAllDay', function () {
    it('is false when the start is null', function () {
        expect(AllDayDetector::isAllDay(null, localInstant('2026-06-15 00:00')))->toBeFalse();
    });

    it('is true for a midnight start with a null end', function () {
        expect(AllDayDetector::isAllDay(localInstant('2026-06-15 00:00'), null))->toBeTrue();
    });

    it('is true for a midnight start ending at 23:59', function () {
        expect(AllDayDetector::isAllDay(
            localInstant('2026-06-15 00:00'),
            localInstant('2026-06-15 23:59'),
        ))->toBeTrue();
    });

    it('is true for a single day midnight to next midnight', function () {
        expect(AllDayDetector::isAllDay(
            localInstant('2026-06-15 00:00'),
            localInstant('2026-06-16 00:00'),
        ))->toBeTrue();
    });

    it('is true for a 3-day midnight to midnight span', function () {
        expect(AllDayDetector::isAllDay(
            localInstant('2026-06-15 00:00'),
            localInstant('2026-06-18 00:00'),
        ))->toBeTrue();
    });

    it('is false for a timed 09:00 to 17:00 event', function () {
        expect(AllDayDetector::isAllDay(
            localInstant('2026-06-15 09:00'),
            localInstant('2026-06-15 17:00'),
        ))->toBeFalse();
    });

    it('is false when the start is midnight but the end is timed', function () {
        expect(AllDayDetector::isAllDay(
            localInstant('2026-06-15 00:00'),
            localInstant('2026-06-15 10:00'),
        ))->toBeFalse();
    });

    it('is false when the end is not strictly after the start', function () {
        expect(AllDayDetector::isAllDay(
            localInstant('2026-06-15 00:00'),
            localInstant('2026-06-15 00:00'),
        ))->toBeFalse();
    });
});

describe('AllDayDetector::exclusiveEndDate', function () {
    it('defaults a null end to the day after the start', function () {
        $end = AllDayDetector::exclusiveEndDate(localInstant('2026-06-15 00:00'), null);

        expect($end->format('Ymd'))->toBe('20260616');
    });

    it('treats a 23:59 end as inclusive, so the exclusive end is the following midnight', function () {
        $end = AllDayDetector::exclusiveEndDate(
            localInstant('2026-06-15 00:00'),
            localInstant('2026-06-17 23:59'),
        );

        expect($end->format('Ymd'))->toBe('20260618');
    });

    it('treats a midnight end as already exclusive', function () {
        $end = AllDayDetector::exclusiveEndDate(
            localInstant('2026-06-15 00:00'),
            localInstant('2026-06-18 00:00'),
        );

        expect($end->format('Ymd'))->toBe('20260618');
    });
});

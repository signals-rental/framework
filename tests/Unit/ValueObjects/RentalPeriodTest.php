<?php

use App\Enums\BasePeriod;
use App\Enums\DayType;
use App\ValueObjects\RentalPeriod;
use Illuminate\Support\Carbon;

function period(string $start, string $end): RentalPeriod
{
    return new RentalPeriod(Carbon::parse($start), Carbon::parse($end));
}

it('exposes start and end as readonly properties', function () {
    $start = Carbon::parse('2026-01-12 14:00:00');
    $end = Carbon::parse('2026-01-15 08:45:00');
    $rentalPeriod = new RentalPeriod($start, $end);

    expect($rentalPeriod->start)->toBe($start)
        ->and($rentalPeriod->end)->toBe($end);
});

it('charges a single unit for an exact one-day rental', function () {
    // Mon 2026-01-12 00:00 -> Tue 2026-01-13 00:00 = 1440 minutes exactly.
    expect(period('2026-01-12 00:00', '2026-01-13 00:00')->chargeableUnits(BasePeriod::Daily, []))
        ->toBe(1);
});

it('floors very short rentals to a minimum of one unit', function () {
    // 5 minutes is well under a day but must still cost one chargeable unit.
    expect(period('2026-01-12 09:00', '2026-01-12 09:05')->chargeableUnits(BasePeriod::Daily, []))
        ->toBe(1);
});

it('charges a second day only once the leeway is exceeded', function (string $end, int $expected) {
    expect(period('2026-01-12 00:00', $end)->chargeableUnits(BasePeriod::Daily, [
        'leeway_minutes' => 30,
    ]))->toBe($expected);
})->with([
    'one day + 20 minutes stays at one unit' => ['2026-01-13 00:20', 1],
    'one day + exactly 30 minutes stays at one unit' => ['2026-01-13 00:30', 1],
    'one day + 35 minutes rolls into a second unit' => ['2026-01-13 00:35', 2],
]);

it('drops the final partial day when returned before the last-day cut-off (spec example 3)', function () {
    // Daily Rate, leeway 30m, last-day cut-off 09:00, Mon 14:00 -> Thu 08:45 => 3 units.
    // 2026-01-12 is a Monday, 2026-01-15 is the Thursday.
    expect(period('2026-01-12 14:00', '2026-01-15 08:45')->chargeableUnits(BasePeriod::Daily, [
        'leeway_minutes' => 30,
        'last_day_cutoff' => '09:00',
    ]))->toBe(3);
});

it('keeps the final day when returned after the last-day cut-off', function () {
    // Same window but returning at 09:30 (after the 09:00 cut-off) keeps Thursday.
    // Mon 14:00 -> Thu 09:30 clock = 2d 19h30m = 4050m, minus 30m leeway = 4020m, ceil(4020/1440) = 3.
    expect(period('2026-01-12 14:00', '2026-01-15 09:30')->chargeableUnits(BasePeriod::Daily, [
        'leeway_minutes' => 30,
        'last_day_cutoff' => '09:00',
    ]))->toBe(3);
});

it('charges the first day in full when collection is after the first-day cut-off', function () {
    // Pick up Mon 14:00 (after the 12:00 cut-off), return Tue 11:00. The late
    // collection must not shrink the first day: the start floors to Mon 00:00 so
    // Monday is billed in full (Mon 00:00 -> Tue 11:00 = 35h => 2 units).
    $withoutCutoff = period('2026-01-12 14:00', '2026-01-13 11:00')
        ->chargeableUnits(BasePeriod::Daily, []);
    $withCutoff = period('2026-01-12 14:00', '2026-01-13 11:00')
        ->chargeableUnits(BasePeriod::Daily, ['first_day_cutoff' => '12:00']);

    expect($withoutCutoff)->toBe(1)
        ->and($withCutoff)->toBe(2);
});

it('leaves the count unchanged when collection is at or before the first-day cut-off', function () {
    // Pick up Mon 09:00 (before the 12:00 cut-off): no flooring, so a 23h rental
    // (Mon 09:00 -> Tue 08:00) stays a single chargeable day.
    expect(period('2026-01-12 09:00', '2026-01-13 08:00')->chargeableUnits(BasePeriod::Daily, [
        'first_day_cutoff' => '12:00',
    ]))->toBe(1);
});

it('scales a rental week by rental_days_per_week', function () {
    // 5 calendar days. With a 5-day rental week (7200 minutes) that is exactly one week.
    expect(period('2026-01-12 00:00', '2026-01-17 00:00')->chargeableUnits(BasePeriod::Weekly, [
        'rental_days_per_week' => 5,
    ]))->toBe(1);

    // 6 calendar days with a 5-day rental week spills into a second week.
    expect(period('2026-01-12 00:00', '2026-01-18 00:00')->chargeableUnits(BasePeriod::Weekly, [
        'rental_days_per_week' => 5,
    ]))->toBe(2);
});

it('defaults a rental week to seven days', function () {
    // 7 calendar days = one default week.
    expect(period('2026-01-12 00:00', '2026-01-19 00:00')->chargeableUnits(BasePeriod::Weekly, []))
        ->toBe(1);
});

it('treats a zero or negative rental_days_per_week as one day to avoid a divide-by-zero', function () {
    // A malformed config of 0 days/week must not crash; it falls back to a 1-day week.
    expect(period('2026-01-12 00:00', '2026-01-13 00:00')->chargeableUnits(BasePeriod::Weekly, [
        'rental_days_per_week' => 0,
    ]))->toBe(1);
});

it('counts only business-hours minutes when day type is business', function () {
    // Business hours 09:00-17:00 (480 min/day). Mon 09:00 -> Fri 17:00.
    // Mon..Fri = 5 business days x 480 = 2400 minutes; ceil(2400 / 1440) = 2.
    expect(period('2026-01-12 09:00', '2026-01-16 17:00')->chargeableUnits(BasePeriod::Daily, [
        'day_type' => DayType::Business,
        'business_hours_start' => '09:00',
        'business_hours_end' => '17:00',
    ]))->toBe(2);
});

it('counts clock minutes for the same window when day type is clock', function () {
    // Clock: Mon 09:00 -> Fri 17:00 = 4 days 8h = 6240 minutes; ceil(6240 / 1440) = 5.
    expect(period('2026-01-12 09:00', '2026-01-16 17:00')->chargeableUnits(BasePeriod::Daily, [
        'day_type' => DayType::Clock,
    ]))->toBe(5);
});

it('clips business-hours minutes to partial first and last days', function () {
    // Business hours 09:00-17:00. Mon 10:00 -> Wed 12:00.
    // Mon 10:00-17:00 = 420, Tue full = 480, Wed 09:00-12:00 = 180 => 1080 minutes.
    // ceil(1080 / 1440) = 1.
    expect(period('2026-01-12 10:00', '2026-01-14 12:00')->chargeableUnits(BasePeriod::Daily, [
        'day_type' => DayType::Business,
        'business_hours_start' => '09:00',
        'business_hours_end' => '17:00',
    ]))->toBe(1);
});

it('ignores hours outside the business window entirely', function () {
    // Rental wholly outside business hours (18:00 -> 20:00) yields zero business minutes,
    // floored to one chargeable unit.
    expect(period('2026-01-12 18:00', '2026-01-12 20:00')->chargeableUnits(BasePeriod::Daily, [
        'day_type' => DayType::Business,
        'business_hours_start' => '09:00',
        'business_hours_end' => '17:00',
    ]))->toBe(1);
});

it('yields zero business minutes when the cut-off collapses the window to nothing', function () {
    // Same-day rental: start 14:00, return 08:00 (before the 09:00 last-day cut-off),
    // so the final partial day is dropped, leaving an effective end (start of day)
    // that is at or before the start. businessHoursMinutes returns 0, floored to one unit.
    expect(period('2026-01-12 14:00', '2026-01-12 08:00')->chargeableUnits(BasePeriod::Daily, [
        'day_type' => DayType::Business,
        'business_hours_start' => '09:00',
        'business_hours_end' => '17:00',
        'last_day_cutoff' => '09:00',
    ]))->toBe(1);
});

it('charges hourly units against the clock', function () {
    // 3h15m = 195 minutes; ceil(195 / 60) = 4 hourly units.
    expect(period('2026-01-12 09:00', '2026-01-12 12:15')->chargeableUnits(BasePeriod::Hourly, []))
        ->toBe(4);
});

it('ignores the last-day cut-off for clock-based periods', function () {
    // Cut-offs are day-based and must not apply to hourly rates. Mon 14:00 -> Tue
    // 08:00 = 18h. A last-day cut-off of 09:00 would (wrongly) floor the end to
    // Tue 00:00 and bill only 10h; the hourly rate must keep the full 18 units.
    expect(period('2026-01-12 14:00', '2026-01-13 08:00')->chargeableUnits(BasePeriod::Hourly, [
        'last_day_cutoff' => '09:00',
    ]))->toBe(18);
});

it('ignores the first-day cut-off for clock-based periods', function () {
    // Mon 14:00 -> Mon 18:00 = 4h. A first-day cut-off of 12:00 would (wrongly)
    // floor the start to Mon 00:00 and bill 18h; the hourly rate must keep 4 units.
    expect(period('2026-01-12 14:00', '2026-01-12 18:00')->chargeableUnits(BasePeriod::Hourly, [
        'first_day_cutoff' => '12:00',
    ]))->toBe(4);
});

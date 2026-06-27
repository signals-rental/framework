<?php

use App\Enums\BasePeriod;
use App\ValueObjects\RentalPeriod;
use Illuminate\Support\Carbon;

it('yields zero clock minutes for an inverted window, floored to one unit', function () {
    // Clock-based (default day_type), end BEFORE start. The duration guard must
    // return 0 minutes rather than a positive over-charge; floored to one unit.
    $rentalPeriod = new RentalPeriod(
        Carbon::parse('2026-01-12 14:00'),
        Carbon::parse('2026-01-12 10:00'),
    );

    expect($rentalPeriod->chargeableUnits(BasePeriod::Daily, []))->toBe(1);
});

it('also yields zero clock minutes when end equals start exactly', function () {
    $at = Carbon::parse('2026-01-12 09:00');
    $rentalPeriod = new RentalPeriod($at->copy(), $at->copy());

    expect($rentalPeriod->chargeableUnits(BasePeriod::Hourly, []))->toBe(1);
});

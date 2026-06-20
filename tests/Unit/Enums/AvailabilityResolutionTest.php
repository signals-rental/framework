<?php

use App\Enums\AvailabilityResolution;

describe('AvailabilityResolution slot helpers', function () {
    it('reports the slot length in minutes per resolution', function () {
        expect(AvailabilityResolution::Daily->slotMinutes())->toBe(1440)
            ->and(AvailabilityResolution::HalfDaily->slotMinutes())->toBe(360)
            ->and(AvailabilityResolution::Hourly->slotMinutes())->toBe(60);
    });

    it('reports the slot length in whole hours per resolution', function () {
        expect(AvailabilityResolution::Daily->slotHours())->toBe(24)
            ->and(AvailabilityResolution::HalfDaily->slotHours())->toBe(6)
            ->and(AvailabilityResolution::Hourly->slotHours())->toBe(1);
    });

    it('reports how many slots make up a calendar day', function () {
        expect(AvailabilityResolution::Daily->slotsPerDay())->toBe(1)
            ->and(AvailabilityResolution::HalfDaily->slotsPerDay())->toBe(4)
            ->and(AvailabilityResolution::Hourly->slotsPerDay())->toBe(24);
    });

    it('flags daily-or-coarser resolutions for one-cell-per-day rendering', function () {
        expect(AvailabilityResolution::Daily->isDailyOrCoarser())->toBeTrue()
            ->and(AvailabilityResolution::HalfDaily->isDailyOrCoarser())->toBeFalse()
            ->and(AvailabilityResolution::Hourly->isDailyOrCoarser())->toBeFalse();
    });

    it('keeps slotHours consistent with slotMinutes', function () {
        foreach (AvailabilityResolution::cases() as $resolution) {
            expect($resolution->slotHours() * 60)->toBe($resolution->slotMinutes())
                ->and($resolution->slotsPerDay() * $resolution->slotMinutes())->toBe(1440);
        }
    });
});

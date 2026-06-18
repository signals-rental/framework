<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Services\Availability\SlotCalculator;
use Illuminate\Support\Carbon;

/**
 * Build a SlotCalculator whose resolution is fixed to the given value, so the
 * unit tests are independent of the `availability.resolution` setting.
 */
function slotCalculator(AvailabilityResolution $resolution): SlotCalculator
{
    $provider = new class($resolution) implements AvailabilityResolutionProvider
    {
        public function __construct(private AvailabilityResolution $resolution) {}

        public function resolve(): AvailabilityResolution
        {
            return $this->resolution;
        }
    };

    return new SlotCalculator($provider);
}

describe('daily resolution', function () {
    it('aligns to local midnight expressed as UTC for a positive-offset timezone', function () {
        $calc = slotCalculator(AvailabilityResolution::Daily);

        // 02:00 UTC on 2 Mar is still 1 Mar 21:00 in New York (UTC-5), so the
        // local-midnight slot start is 1 Mar 05:00 UTC.
        $aligned = $calc->alignToSlot(Carbon::parse('2026-03-02T02:00:00Z'), 'America/New_York');

        expect($aligned->toIso8601String())->toBe('2026-03-01T05:00:00+00:00');
    });

    it('aligns to local midnight for a behind-UTC store using London time', function () {
        $calc = slotCalculator(AvailabilityResolution::Daily);

        // In March, London is on GMT (UTC+0), so local midnight == UTC midnight.
        $aligned = $calc->alignToSlot(Carbon::parse('2026-03-02T14:30:00Z'), 'Europe/London');

        expect($aligned->toIso8601String())->toBe('2026-03-02T00:00:00+00:00');
    });

    it('generates one 24h slot per day across a range', function () {
        $calc = slotCalculator(AvailabilityResolution::Daily);

        $slots = $calc->generateSlots(
            Carbon::parse('2026-03-01T00:00:00Z'),
            Carbon::parse('2026-03-04T00:00:00Z'),
            'UTC',
        );

        expect($slots)->toHaveCount(3)
            ->and($slots[0]->toIso8601String())->toBe('2026-03-01T00:00:00+00:00')
            ->and($slots[1]->toIso8601String())->toBe('2026-03-02T00:00:00+00:00')
            ->and($slots[2]->toIso8601String())->toBe('2026-03-03T00:00:00+00:00');
    });

    it('falls back to the application timezone when the store has none', function () {
        $calc = slotCalculator(AvailabilityResolution::Daily);

        $aligned = $calc->alignToSlot(Carbon::parse('2026-03-02T14:30:00Z'), null);

        // The test app timezone is UTC, so this is UTC midnight.
        expect($aligned->toIso8601String())->toBe('2026-03-02T00:00:00+00:00');
    });
});

describe('half-daily resolution', function () {
    it('floors the hour-of-day to the nearest 6h boundary in local time', function () {
        $calc = slotCalculator(AvailabilityResolution::HalfDaily);

        // 14:30 UTC falls in the 12:00 local slot (UTC store timezone).
        $aligned = $calc->alignToSlot(Carbon::parse('2026-03-02T14:30:00Z'), 'UTC');

        expect($aligned->toIso8601String())->toBe('2026-03-02T12:00:00+00:00');
    });

    it('generates four 6h slots across a day', function () {
        $calc = slotCalculator(AvailabilityResolution::HalfDaily);

        $slots = $calc->generateSlots(
            Carbon::parse('2026-03-02T00:00:00Z'),
            Carbon::parse('2026-03-03T00:00:00Z'),
            'UTC',
        );

        expect($slots)->toHaveCount(4)
            ->and(array_map(fn ($s) => $s->format('H:i'), $slots))->toBe(['00:00', '06:00', '12:00', '18:00']);
    });
});

describe('hourly resolution', function () {
    it('aligns to the top of the UTC hour regardless of store timezone', function () {
        $calc = slotCalculator(AvailabilityResolution::Hourly);

        $aligned = $calc->alignToSlot(Carbon::parse('2026-03-02T14:37:12Z'), 'America/New_York');

        expect($aligned->toIso8601String())->toBe('2026-03-02T14:00:00+00:00');
    });

    it('generates one slot per hour across a range', function () {
        $calc = slotCalculator(AvailabilityResolution::Hourly);

        $slots = $calc->generateSlots(
            Carbon::parse('2026-03-02T09:00:00Z'),
            Carbon::parse('2026-03-02T12:00:00Z'),
            'UTC',
        );

        expect($slots)->toHaveCount(3)
            ->and(array_map(fn ($s) => $s->format('H:i'), $slots))->toBe(['09:00', '10:00', '11:00']);
    });
});

describe('roundUpToSlot', function () {
    it('returns an already-aligned instant unchanged', function () {
        $calc = slotCalculator(AvailabilityResolution::Daily);

        $rounded = $calc->roundUpToSlot(Carbon::parse('2026-03-02T00:00:00Z'), 'UTC');

        expect($rounded->toIso8601String())->toBe('2026-03-02T00:00:00+00:00');
    });

    it('rounds a mid-slot instant up to the next boundary', function () {
        $calc = slotCalculator(AvailabilityResolution::Daily);

        $rounded = $calc->roundUpToSlot(Carbon::parse('2026-03-02T10:00:00Z'), 'UTC');

        expect($rounded->toIso8601String())->toBe('2026-03-03T00:00:00+00:00');
    });
});

describe('slot-count safety ceiling', function () {
    it('throws when a window would exceed the maximum slot count', function () {
        config(['availability.max_slots_per_recalculation' => 10]);

        $calc = slotCalculator(AvailabilityResolution::Daily);

        // 30 daily slots requested against a ceiling of 10 — must throw rather
        // than materialise an unbounded list (guards against a sentinel demand).
        expect(fn () => $calc->generateSlots(
            Carbon::parse('2026-03-01T00:00:00Z'),
            Carbon::parse('2026-03-31T00:00:00Z'),
            'UTC',
        ))->toThrow(RuntimeException::class);
    });

    it('does not throw for a window within the ceiling', function () {
        config(['availability.max_slots_per_recalculation' => 10]);

        $calc = slotCalculator(AvailabilityResolution::Daily);

        $slots = $calc->generateSlots(
            Carbon::parse('2026-03-01T00:00:00Z'),
            Carbon::parse('2026-03-06T00:00:00Z'),
            'UTC',
        );

        expect($slots)->toHaveCount(5);
    });
});

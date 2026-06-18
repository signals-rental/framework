<?php

namespace App\Services\Availability;

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Computes availability slot boundaries for the active resolution and a store's
 * local timezone.
 *
 * The resolution is read through the {@see AvailabilityResolutionProvider}
 * contract (never `settings()` directly) so the Cloud package can enforce it
 * per tenant. Boundaries are aligned in the store's local timezone and returned
 * as UTC instants:
 *
 *  - **Daily** — one 24h slot per local calendar day, aligned to local midnight.
 *  - **HalfDaily** — 6h slots at 00:00 / 06:00 / 12:00 / 18:00 local time.
 *  - **Hourly** — 1h slots aligned to the top of each UTC hour (resolution is
 *    intra-day precision, so the design pins these to UTC rather than local
 *    time, avoiding fractional-offset timezone surprises).
 *
 * All returned Carbon instances are in UTC. The store timezone falls back to the
 * application timezone when null/blank.
 */
class SlotCalculator
{
    public function __construct(
        private readonly AvailabilityResolutionProvider $resolutionProvider,
    ) {}

    /**
     * The active resolution.
     */
    public function resolution(): AvailabilityResolution
    {
        return $this->resolutionProvider->resolve();
    }

    /**
     * The slot length in whole hours for the active resolution.
     */
    public function slotHours(): int
    {
        return match ($this->resolution()) {
            AvailabilityResolution::Daily => 24,
            AvailabilityResolution::HalfDaily => 6,
            AvailabilityResolution::Hourly => 1,
        };
    }

    /**
     * Floor an instant to the start of the slot that contains it, as a UTC
     * instant. The given timezone is the store's local timezone; daily and
     * half-daily boundaries are computed there before being expressed in UTC.
     */
    public function alignToSlot(Carbon $instant, ?string $timezone = null): Carbon
    {
        $resolution = $this->resolution();

        if ($resolution === AvailabilityResolution::Hourly) {
            // Hourly aligns to the top of the UTC hour directly.
            return $instant->copy()->utc()->startOfHour();
        }

        $tz = $this->normaliseTimezone($timezone);
        $local = $instant->copy()->setTimezone($tz);

        if ($resolution === AvailabilityResolution::Daily) {
            $local = $local->startOfDay();
        } else {
            // HalfDaily: floor the hour-of-day to the nearest 6-hour boundary.
            $boundaryHour = intdiv($local->hour, 6) * 6;
            $local = $local->startOfDay()->addHours($boundaryHour);
        }

        return $local->utc();
    }

    /**
     * Round an instant up to the next slot boundary (returning the instant
     * unchanged if it already sits exactly on one), as a UTC instant.
     */
    public function roundUpToSlot(Carbon $instant, ?string $timezone = null): Carbon
    {
        $aligned = $this->alignToSlot($instant, $timezone);

        if ($aligned->equalTo($instant->copy()->utc())) {
            return $aligned;
        }

        return $this->advance($aligned, $timezone);
    }

    /**
     * Generate the aligned slot-start instants (UTC) covering the half-open
     * window `[from, to)`. The first slot is the one containing `from`; slots
     * continue while their start is before `to`.
     *
     * Belt-and-suspenders: the loop is bounded by
     * `availability.max_slots_per_recalculation`. Callers are expected to clamp
     * their windows to the rolling horizon (see {@see RecalculationPipeline}),
     * but this hard ceiling guarantees no future caller can re-introduce an
     * unbounded slot blow-up (e.g. an open-ended/sentinel-dated demand at hourly
     * resolution). Exceeding it throws rather than silently truncating.
     *
     * @return list<Carbon>
     *
     * @throws RuntimeException when the requested span would exceed the maximum
     *                          slot count
     */
    public function generateSlots(Carbon $from, Carbon $to, ?string $timezone = null): array
    {
        $slots = [];
        $cursor = $this->alignToSlot($from, $timezone);
        $end = $to->copy()->utc();

        // Guard against a zero/negative window producing no slots while still
        // emitting the slot that contains `from` when from == to.
        if (! $cursor->lessThan($end)) {
            return [$cursor];
        }

        $maxSlots = $this->maxSlots();

        while ($cursor->lessThan($end)) {
            if (count($slots) >= $maxSlots) {
                throw new RuntimeException(sprintf(
                    'Availability slot generation exceeded the maximum of %d slots for the requested window. '.
                    'The window must be clamped to the rolling snapshot horizon before generating slots.',
                    $maxSlots,
                ));
            }

            $slots[] = $cursor;
            $cursor = $this->advance($cursor, $timezone);
        }

        return $slots;
    }

    /**
     * The hard ceiling on slots a single generation may produce, read from
     * config with a safe default.
     */
    private function maxSlots(): int
    {
        return max(1, (int) config('availability.max_slots_per_recalculation', 50000));
    }

    /**
     * Advance an aligned slot start to the next slot start (UTC). For daily and
     * half-daily this re-aligns in local time so DST transitions keep slots
     * pinned to local boundaries.
     */
    public function advance(Carbon $alignedUtc, ?string $timezone = null): Carbon
    {
        $resolution = $this->resolution();

        if ($resolution === AvailabilityResolution::Hourly) {
            return $alignedUtc->copy()->addHour();
        }

        $tz = $this->normaliseTimezone($timezone);
        $local = $alignedUtc->copy()->setTimezone($tz)->addHours($this->slotHours());

        return $local->utc();
    }

    /**
     * Resolve the timezone to use, defaulting to the application timezone when
     * the store has none.
     */
    private function normaliseTimezone(?string $timezone): string
    {
        if ($timezone === null || trim($timezone) === '') {
            return (string) config('app.timezone', 'UTC');
        }

        return $timezone;
    }
}

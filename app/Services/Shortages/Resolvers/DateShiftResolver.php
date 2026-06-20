<?php

namespace App\Services\Shortages\Resolvers;

use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Models\Demand;
use App\Services\AvailabilityService;
use App\Services\Shortages\ShortageEventRecorder;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;

/**
 * Date shift (shortage-resolution-sub-hires.md §4.4).
 *
 * Searches adjacent date ranges (forward and backward, in day increments) where
 * the full requested quantity is available, and offers each as an option.
 * Execution does NOT mutate the opportunity — per the spec, it records the
 * proposed shift (original vs shifted dates) and the user confirms the change
 * through the normal edit flow. Self-contained (it only reads the availability
 * engine) so it records a Confirmed resolution capturing the proposed window.
 */
class DateShiftResolver extends AbstractShortageResolver
{
    /** Days to search either side of the requested window. */
    private const int SEARCH_DAYS = 3;

    public function __construct(
        ShortageEventRecorder $events,
        private readonly AvailabilityService $availability,
    ) {
        parent::__construct($events);
    }

    public function key(): string
    {
        return 'date_shift';
    }

    public function name(): string
    {
        return 'Shift dates';
    }

    public function priority(): int
    {
        return 40;
    }

    public function isAutoExecutable(): bool
    {
        return false;
    }

    /**
     * Only worth offering for definite (non-indefinite) windows we can shift.
     */
    public function canResolve(Shortage $shortage): bool
    {
        return $shortage->remainingShortfall() > 0
            && $shortage->endsAt->lessThan(Demand::sentinel());
    }

    /**
     * @return list<ResolutionOption>
     */
    public function getOptions(Shortage $shortage): array
    {
        if (! $this->canResolve($shortage)) {
            return [];
        }

        // diffInSeconds is signed in Carbon 3 — take the absolute span so a shifted
        // window never ends before it starts (which would build an invalid tstzrange).
        $durationSeconds = abs($shortage->startsAt->diffInSeconds($shortage->endsAt));
        $options = [];

        foreach ($this->candidateOffsets() as $offsetDays) {
            $start = $shortage->startsAt->copy()->addDays($offsetDays);
            $end = $start->copy()->addSeconds((int) $durationSeconds);

            $available = $this->availability->availableForItem(
                $shortage->productId,
                $shortage->storeId,
                $start,
                $end,
                'opportunity_item',
                $shortage->opportunityItemId,
            );

            if ($available < $shortage->requestedQuantity) {
                continue;
            }

            $direction = $offsetDays > 0 ? 'later' : 'earlier';

            $options[] = new ResolutionOption(
                resolverKey: $this->key(),
                type: ShortageResolutionType::DateShift,
                label: sprintf('Shift %d day(s) %s', abs($offsetDays), $direction),
                description: sprintf(
                    'Full quantity (%d) is available %s, from %s.',
                    $shortage->requestedQuantity,
                    $start->toDateString(),
                    $start->toIso8601String(),
                ),
                quantityResolved: $shortage->requestedQuantity,
                isPartial: false,
                autoExecutable: false,
                metadata: [
                    'shifted_starts_at' => $start->utc()->toIso8601String(),
                    'shifted_ends_at' => $end->utc()->toIso8601String(),
                    'offset_days' => $offsetDays,
                ],
            );
        }

        return $options;
    }

    public function apply(Shortage $shortage, ResolutionOption $option): ResolutionResult
    {
        $resolution = $this->record(
            shortage: $shortage,
            quantityResolved: $shortage->requestedQuantity,
            status: ShortageResolutionStatus::Confirmed,
            metadata: [
                'original_starts_at' => $shortage->startsAt->utc()->toIso8601String(),
                'original_ends_at' => $shortage->endsAt->utc()->toIso8601String(),
                'shifted_starts_at' => $option->metadata['shifted_starts_at'] ?? null,
                'shifted_ends_at' => $option->metadata['shifted_ends_at'] ?? null,
            ],
        );

        return ResolutionResult::confirmed(
            $resolution,
            'Recorded a date-shift proposal; apply the new dates through the opportunity edit flow.',
        );
    }

    protected function resolutionType(): ShortageResolutionType
    {
        return ShortageResolutionType::DateShift;
    }

    /**
     * Offsets to probe: nearest days first, alternating earlier/later.
     *
     * @return list<int>
     */
    private function candidateOffsets(): array
    {
        $offsets = [];

        for ($day = 1; $day <= self::SEARCH_DAYS; $day++) {
            $offsets[] = $day;
            $offsets[] = -$day;
        }

        return $offsets;
    }
}

<?php

namespace App\Services\Shortages\Resolvers;

use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Enums\WaitlistMonitorStatus;
use App\Jobs\ExpireWaitlistMonitors;
use App\Listeners\Availability\MatchWaitlistMonitors;
use App\Models\ShortageWaitlistMonitor;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;

/**
 * Waitlist (shortage-resolution-sub-hires.md §4.6).
 *
 * Always offers exactly one option: add the shortage to the waitlist — a
 * monitoring mechanism that watches for availability changes that would satisfy
 * it. Self-contained: it records a Monitoring resolution AND a durable
 * {@see ShortageWaitlistMonitor} row capturing the product, store, quantity, and
 * window, then fires `shortage.waitlist.created`. The
 * {@see MatchWaitlistMonitors} listener flips the
 * monitor to Matched when stock frees up; the scheduled
 * {@see ExpireWaitlistMonitors} job retires stale ones.
 */
class WaitlistResolver extends AbstractShortageResolver
{
    /** Default monitor lifetime (days) when no item end date bounds it. */
    private const int DEFAULT_EXPIRY_DAYS = 30;

    public function key(): string
    {
        return 'waitlist';
    }

    public function name(): string
    {
        return 'Waitlist';
    }

    public function priority(): int
    {
        return 90;
    }

    public function isAutoExecutable(): bool
    {
        return false;
    }

    /**
     * Always applicable — the waitlist is the fallback when nothing else resolves
     * the shortage.
     */
    public function canResolve(Shortage $shortage): bool
    {
        return $shortage->remainingShortfall() > 0;
    }

    /**
     * @return list<ResolutionOption>
     */
    public function getOptions(Shortage $shortage): array
    {
        if (! $this->canResolve($shortage)) {
            return [];
        }

        return [
            new ResolutionOption(
                resolverKey: $this->key(),
                type: ShortageResolutionType::Waitlist,
                label: 'Add to waitlist',
                description: "Monitor availability and notify when {$shortage->remainingShortfall()} unit(s) of {$shortage->productName} become free.",
                quantityResolved: $shortage->remainingShortfall(),
                isPartial: false,
                autoExecutable: false,
            ),
        ];
    }

    public function apply(Shortage $shortage, ResolutionOption $option): ResolutionResult
    {
        $resolution = $this->record(
            shortage: $shortage,
            quantityResolved: $shortage->remainingShortfall(),
            status: ShortageResolutionStatus::Monitoring,
            metadata: [
                'quantity_needed' => $shortage->remainingShortfall(),
                'starts_at' => $shortage->startsAt->utc()->toIso8601String(),
                'ends_at' => $shortage->endsAt->utc()->toIso8601String(),
            ],
        );

        // The monitor expires when the hire window ends (no point watching past
        // it), or after a default horizon when the window is open-ended.
        $expiresAt = $shortage->endsAt;
        $defaultExpiry = now()->addDays(self::DEFAULT_EXPIRY_DAYS);
        if ($expiresAt->greaterThan($defaultExpiry->copy()->addYears(50))) {
            $expiresAt = $defaultExpiry;
        }

        $monitor = ShortageWaitlistMonitor::query()->create([
            'shortage_resolution_id' => $resolution->id,
            'opportunity_item_id' => $shortage->opportunityItemId,
            'product_id' => $shortage->productId,
            'store_id' => $shortage->storeId,
            'quantity_needed' => $shortage->remainingShortfall(),
            'starts_at' => $shortage->startsAt,
            'ends_at' => $shortage->endsAt,
            'status' => WaitlistMonitorStatus::Active->value,
            'expires_at' => $expiresAt,
        ]);

        $this->events->waitlistCreated($monitor);

        return ResolutionResult::monitoring(
            $resolution,
            "Added {$shortage->remainingShortfall()} unit(s) of {$shortage->productName} to the waitlist.",
        );
    }

    protected function resolutionType(): ShortageResolutionType
    {
        return ShortageResolutionType::Waitlist;
    }
}

<?php

namespace App\Services\Shortages\Resolvers;

use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;

/**
 * Waitlist (shortage-resolution-sub-hires.md §4.6).
 *
 * Always offers exactly one option: add the shortage to the waitlist — a
 * monitoring mechanism that watches for availability changes that would satisfy
 * it. Self-contained: it records a Monitoring resolution capturing the product,
 * store, quantity, and window in metadata. The availability-change listener that
 * fires `shortage.waitlist.matched` is a later wiring step; the durable
 * monitoring record is created here.
 */
class WaitlistResolver extends AbstractShortageResolver
{
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

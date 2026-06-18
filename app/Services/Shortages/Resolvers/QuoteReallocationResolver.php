<?php

namespace App\Services\Shortages\Resolvers;

use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;

/**
 * Quote reallocation (shortage-resolution-sub-hires.md §4.1).
 *
 * Releases stock held by unconfirmed quotes overlapping the shortage so it frees
 * up for a confirmed order. Generating concrete options and executing the release
 * requires the competing-quote line-item "unbooked" state transition and the
 * owner-notification event, which land with later milestones. Until then this
 * resolver records the reallocation INTENT as a pending resolution (noting the
 * dependency) so the audit trail and downstream consumers exist from the outset.
 */
class QuoteReallocationResolver extends AbstractShortageResolver
{
    public function key(): string
    {
        return 'reallocate';
    }

    public function name(): string
    {
        return 'Reallocate from quote';
    }

    public function priority(): int
    {
        return 10;
    }

    public function isAutoExecutable(): bool
    {
        return false;
    }

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
                type: ShortageResolutionType::Reallocate,
                label: 'Release stock held by a competing quote',
                description: 'Free up to '.$shortage->remainingShortfall().' unit(s) from an overlapping unconfirmed quote. Awaits the quote-release transition (later milestone).',
                quantityResolved: $shortage->remainingShortfall(),
                isPartial: false,
                autoExecutable: false,
                metadata: ['pending_dependency' => 'quote_release'],
            ),
        ];
    }

    public function apply(Shortage $shortage, ResolutionOption $option): ResolutionResult
    {
        $resolution = $this->record(
            shortage: $shortage,
            quantityResolved: $shortage->remainingShortfall(),
            status: ShortageResolutionStatus::Pending,
            metadata: ['pending_dependency' => 'quote_release'],
        );

        return ResolutionResult::pending(
            $resolution,
            'Recorded reallocation intent; the quote-release transition is not yet available.',
            followupType: 'approval',
        );
    }

    protected function resolutionType(): ShortageResolutionType
    {
        return ShortageResolutionType::Reallocate;
    }
}

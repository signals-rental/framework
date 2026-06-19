<?php

namespace App\Services\Shortages\Resolvers;

use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;

/**
 * Partial fulfilment (shortage-resolution-sub-hires.md §4.5).
 *
 * Always offers exactly one option: fulfil with the available quantity instead of
 * the requested quantity. When applied it records a Confirmed resolution covering
 * the available units and captures the original→reduced quantities in metadata for
 * audit. The line item's quantity edit itself remains the user's explicit edit
 * flow (an ItemQuantityChanged event), so the resolver stays decoupled from the
 * Verbs write path.
 *
 * **NOT auto-executable** (spec §4.5): reducing a line quantity requires business
 * judgement and must never happen without explicit confirmation, so the
 * {@see ShortageAutoResolver} will not silently apply it.
 */
class PartialFulfilmentResolver extends AbstractShortageResolver
{
    public function key(): string
    {
        return 'partial';
    }

    public function name(): string
    {
        return 'Partial fulfilment';
    }

    public function priority(): int
    {
        return 50;
    }

    public function isAutoExecutable(): bool
    {
        // Spec §4.5: reducing a line quantity requires business judgement.
        return false;
    }

    /**
     * Applicable whenever some — but not all — of the requested quantity is
     * available; offering "fulfil with zero" is meaningless.
     */
    public function canResolve(Shortage $shortage): bool
    {
        return $shortage->availableQuantity > 0 && $shortage->remainingShortfall() > 0;
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
                type: ShortageResolutionType::Partial,
                label: "Fulfil {$shortage->availableQuantity} of {$shortage->requestedQuantity}",
                description: "Reduce the line to the {$shortage->availableQuantity} available; {$shortage->remainingShortfall()} would remain unfulfilled.",
                quantityResolved: $shortage->availableQuantity,
                isPartial: true,
                autoExecutable: false,
                requiresConfirmation: true,
            ),
        ];
    }

    public function apply(Shortage $shortage, ResolutionOption $option): ResolutionResult
    {
        $resolution = $this->record(
            shortage: $shortage,
            quantityResolved: $shortage->availableQuantity,
            status: ShortageResolutionStatus::Confirmed,
            metadata: [
                'original_quantity' => $shortage->requestedQuantity,
                'reduced_quantity' => $shortage->availableQuantity,
                'unfulfilled' => $shortage->remainingShortfall(),
            ],
        );

        return ResolutionResult::confirmed(
            $resolution,
            "Recorded partial fulfilment of {$shortage->availableQuantity} unit(s).",
        );
    }

    protected function resolutionType(): ShortageResolutionType
    {
        return ShortageResolutionType::Partial;
    }
}

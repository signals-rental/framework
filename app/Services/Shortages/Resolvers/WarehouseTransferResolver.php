<?php

namespace App\Services\Shortages\Resolvers;

use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Models\Store;
use App\Services\AvailabilityService;
use App\Services\Shortages\ShortageEventRecorder;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;

/**
 * Warehouse transfer (shortage-resolution-sub-hires.md §4.3).
 *
 * Only active when more than one warehouse (store) exists. Queries availability
 * at other stores for the same product and window and offers each that can cover
 * the shortfall. Execution creates a `store_transfer` record — an entity not yet
 * built — so this resolver records the transfer INTENT as pending (capturing the
 * source store) and notes the dependency.
 */
class WarehouseTransferResolver extends AbstractShortageResolver
{
    public function __construct(
        ShortageEventRecorder $events,
        private readonly AvailabilityService $availability,
    ) {
        parent::__construct($events);
    }

    public function key(): string
    {
        return 'transfer';
    }

    public function name(): string
    {
        return 'Warehouse transfer';
    }

    public function priority(): int
    {
        return 30;
    }

    public function isAutoExecutable(): bool
    {
        // TODO(M7): make configurable per relationship/warehouse-pair
        return false;
    }

    /**
     * Only worth offering in a multi-warehouse store.
     */
    public function canResolve(Shortage $shortage): bool
    {
        return $shortage->remainingShortfall() > 0 && Store::query()->count() > 1;
    }

    /**
     * @return list<ResolutionOption>
     */
    public function getOptions(Shortage $shortage): array
    {
        if (! $this->canResolve($shortage)) {
            return [];
        }

        $options = [];

        $otherStores = Store::query()
            ->whereKeyNot($shortage->storeId)
            ->get();

        foreach ($otherStores as $store) {
            $available = $this->availability->availableForItem(
                $shortage->productId,
                $store->id,
                $shortage->startsAt,
                $shortage->endsAt,
                // No demand of this item exists at the OTHER store, so the exclude
                // pair simply never matches — full availability is reported.
                'opportunity_item',
                $shortage->opportunityItemId,
            );

            if ($available <= 0) {
                continue;
            }

            $coverage = min($available, $shortage->remainingShortfall());

            $options[] = new ResolutionOption(
                resolverKey: $this->key(),
                type: ShortageResolutionType::Transfer,
                label: "Transfer {$coverage} from {$store->name}",
                description: "{$store->name} has {$available} unit(s) free for the window. Awaits store-transfer creation (later milestone).",
                quantityResolved: $coverage,
                isPartial: $coverage < $shortage->remainingShortfall(),
                autoExecutable: false,
                metadata: ['source_store_id' => $store->id],
            );
        }

        return $options;
    }

    public function apply(Shortage $shortage, ResolutionOption $option): ResolutionResult
    {
        $resolution = $this->record(
            shortage: $shortage,
            quantityResolved: $option->quantityResolved,
            status: ShortageResolutionStatus::Pending,
            metadata: [
                'pending_dependency' => 'store_transfer',
                'source_store_id' => $option->metadata['source_store_id'] ?? null,
            ],
        );

        return ResolutionResult::pending(
            $resolution,
            'Recorded transfer intent; the store-transfer entity is not yet available.',
            followupType: 'delivery',
        );
    }

    protected function resolutionType(): ShortageResolutionType
    {
        return ShortageResolutionType::Transfer;
    }
}

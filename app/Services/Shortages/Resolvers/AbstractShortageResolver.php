<?php

namespace App\Services\Shortages\Resolvers;

use App\Contracts\ShortageResolverContract;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Services\Shortages\ShortageEventRecorder;
use App\ValueObjects\Shortage;
use Illuminate\Support\Facades\DB;

/**
 * Shared machinery for built-in shortage resolvers: recording a
 * {@see ShortageResolution} (and its single per-item allocation row) atomically
 * and emitting the `shortage.resolution.created` event.
 *
 * Each concrete resolver supplies its key/name/priority/auto-executability,
 * decides applicability, generates options, and calls {@see record()} from
 * {@see apply()}. The core framework creates a one-to-one resolution↔item
 * relationship; the batch-resolution plugin overrides to create one-to-many.
 */
abstract class AbstractShortageResolver implements ShortageResolverContract
{
    public function __construct(
        protected readonly ShortageEventRecorder $events,
    ) {}

    /**
     * The resolution type this resolver produces — used on the persisted record
     * and the event payload.
     */
    abstract protected function resolutionType(): ShortageResolutionType;

    /**
     * Persist a resolution record + its single opportunity-item allocation inside
     * one transaction, then emit `shortage.resolution.created`.
     *
     * @param  array<string, mixed>  $metadata  resolver-specific data
     */
    protected function record(
        Shortage $shortage,
        int $quantityResolved,
        ShortageResolutionStatus $status,
        array $metadata = [],
        ?int $cost = null,
    ): ShortageResolution {
        $resolution = DB::transaction(function () use ($shortage, $quantityResolved, $status, $metadata, $cost): ShortageResolution {
            $resolution = ShortageResolution::query()->create([
                'resolver_key' => $this->key(),
                'resolution_type' => $this->resolutionType()->value,
                'status' => $status->value,
                'quantity_resolved' => $quantityResolved,
                'cost' => $cost,
                // Stamp product/store so resolution-scoped availability events can
                // be logged without re-deriving them from the (transient) shortage.
                'metadata' => $metadata + [
                    'product_id' => $shortage->productId,
                    'store_id' => $shortage->storeId,
                    'opportunity_id' => $shortage->opportunityId,
                ],
                'resolved_by' => auth()->id(),
                'confirmed_by' => $status === ShortageResolutionStatus::Confirmed ? auth()->id() : null,
                'confirmed_at' => $status === ShortageResolutionStatus::Confirmed ? now() : null,
            ]);

            ShortageResolutionItem::query()->create([
                'shortage_resolution_id' => $resolution->id,
                'opportunity_item_id' => $shortage->opportunityItemId,
                'quantity_allocated' => $quantityResolved,
            ]);

            return $resolution->load('items');
        });

        $this->events->resolutionCreated($resolution, $shortage);

        return $resolution;
    }
}

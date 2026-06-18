<?php

namespace App\Data\Opportunities;

use App\Data\Concerns\EntityReferenceData;
use App\Data\Concerns\FormatsTimestamps;
use App\Models\OpportunityItemAsset;
use App\Models\StockLevel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * API/serialisation representation of a per-asset assignment on a line item.
 *
 * Status and return-condition are exposed both as raw RMS integers and as
 * human-readable labels. Lifecycle timestamps are ISO-8601 UTC. The assigned
 * stock level is a lazy {id, name} reference — only present when eager-loaded.
 */
class OpportunityItemAssetData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $opportunity_item_id,
        public ?int $stock_level_id,
        public int $status,
        public string $status_label,
        public ?int $container_stock_level_id,
        public ?string $allocated_at,
        public ?string $prepared_at,
        public ?string $dispatched_at,
        public ?string $returned_at,
        public ?string $checked_at,
        public ?int $condition_on_return,
        public ?string $condition_on_return_label,
        public ?string $notes,
        public string $created_at,
        public string $updated_at,
        public Lazy|EntityReferenceData|null $stock_level = null,
    ) {}

    public static function fromModel(OpportunityItemAsset $asset): self
    {
        return new self(
            id: $asset->id,
            opportunity_item_id: $asset->opportunity_item_id,
            stock_level_id: $asset->stock_level_id,
            status: $asset->status->value,
            status_label: $asset->status->label(),
            container_stock_level_id: $asset->container_stock_level_id,
            allocated_at: self::formatNullableTimestamp($asset->allocated_at),
            prepared_at: self::formatNullableTimestamp($asset->prepared_at),
            dispatched_at: self::formatNullableTimestamp($asset->dispatched_at),
            returned_at: self::formatNullableTimestamp($asset->returned_at),
            checked_at: self::formatNullableTimestamp($asset->checked_at),
            condition_on_return: $asset->condition_on_return?->value,
            condition_on_return_label: $asset->condition_on_return?->label(),
            notes: $asset->notes,
            created_at: self::formatTimestamp($asset->created_at),
            updated_at: self::formatTimestamp($asset->updated_at),
            stock_level: Lazy::whenLoaded('stockLevel', $asset, fn (): ?EntityReferenceData => self::reference($asset->stockLevel)),
        );
    }

    private static function reference(?StockLevel $stockLevel): ?EntityReferenceData
    {
        if ($stockLevel === null) {
            return null;
        }

        return EntityReferenceData::from([
            'id' => $stockLevel->id,
            'name' => $stockLevel->item_name ?? $stockLevel->asset_number ?? (string) $stockLevel->id,
        ]);
    }

    private static function formatNullableTimestamp(?\DateTimeInterface $timestamp): ?string
    {
        return $timestamp !== null ? self::formatTimestamp($timestamp) : null;
    }
}

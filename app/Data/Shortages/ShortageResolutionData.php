<?php

namespace App\Data\Shortages;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * API representation of a persisted {@see ShortageResolution}
 * (shortage-resolution-sub-hires.md §8.1).
 */
class ShortageResolutionData extends Data
{
    use FormatsTimestamps;

    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  Lazy|array<int, ShortageResolutionItemData>  $items
     */
    public function __construct(
        public int $id,
        public string $resolver_key,
        public string $resolution_type,
        public string $status,
        public string $status_label,
        public int $quantity_resolved,
        /**
         * Cost in the company base currency as an RMS-compatible decimal string
         * ("125.50"), null when no cost was recorded. Stored as integer minor
         * units in the DB; serialised via {@see ShortageResolution::formatMoneyCost()}.
         */
        public ?string $cost,
        public ?array $metadata,
        public ?int $resolved_by,
        public ?string $confirmed_at,
        public ?string $cancelled_at,
        public ?string $cancellation_reason,
        public ?string $notes,
        public string $created_at,
        public string $updated_at,
        public Lazy|array $items = [],
    ) {}

    public static function fromModel(ShortageResolution $resolution): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $resolution->created_at;
        /** @var Carbon $updatedAt */
        $updatedAt = $resolution->updated_at;

        return new self(
            id: $resolution->id,
            resolver_key: $resolution->resolver_key,
            resolution_type: $resolution->resolution_type->value,
            status: $resolution->status->value,
            status_label: $resolution->status->label(),
            quantity_resolved: $resolution->quantity_resolved,
            cost: $resolution->cost !== null ? $resolution->formatMoneyCost('cost') : null,
            metadata: $resolution->metadata,
            resolved_by: $resolution->resolved_by,
            confirmed_at: self::formatNullableTimestamp($resolution->confirmed_at),
            cancelled_at: self::formatNullableTimestamp($resolution->cancelled_at),
            cancellation_reason: $resolution->cancellation_reason,
            notes: $resolution->notes,
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
            items: Lazy::whenLoaded(
                'items',
                $resolution,
                fn (): array => $resolution->items->map(
                    fn (ShortageResolutionItem $item): ShortageResolutionItemData => ShortageResolutionItemData::fromModel($item)
                )->all(),
            ),
        );
    }
}

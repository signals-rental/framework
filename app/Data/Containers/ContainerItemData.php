<?php

namespace App\Data\Containers;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\ContainerItem;
use Spatie\LaravelData\Data;

/**
 * API/serialisation representation of a container membership row.
 */
class ContainerItemData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $container_id,
        public int $serialised_item_id,
        public int $product_id,
        public string $packed_at,
        public ?int $packed_by_user_id,
        public ?string $unpacked_at,
        public ?string $unpacked_reason,
        public ?int $transferred_to_container_id,
        public ?string $position,
        public ?string $notes,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(ContainerItem $item): self
    {
        return new self(
            id: $item->id,
            container_id: $item->container_id,
            serialised_item_id: $item->serialised_item_id,
            product_id: $item->product_id,
            packed_at: self::formatTimestamp($item->packed_at),
            packed_by_user_id: $item->packed_by_user_id,
            unpacked_at: $item->unpacked_at !== null ? self::formatTimestamp($item->unpacked_at) : null,
            unpacked_reason: $item->unpacked_reason?->value,
            transferred_to_container_id: $item->transferred_to_container_id,
            position: $item->position,
            notes: $item->notes,
            created_at: self::formatTimestamp($item->created_at),
            updated_at: self::formatTimestamp($item->updated_at),
        );
    }
}

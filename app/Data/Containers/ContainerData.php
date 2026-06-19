<?php

namespace App\Data\Containers;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\Container;
use App\Models\ContainerItem;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * API/serialisation representation of a container.
 *
 * Containers are plain Eloquent (not event-sourced). For the M5-3b availability
 * subset this DTO exposes the open/sealed lifecycle plus the active membership
 * rows; the full seal/dissolve/dispatch/return lifecycle and its timestamps stay
 * Phase-4. The packed contents are lazy — only present when `?include=items` is
 * requested.
 */
class ContainerData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public string $uuid,
        public string $name,
        public ?string $barcode,
        public string $status,
        public string $scan_mode,
        public bool $is_temporary,
        public ?int $serialised_item_id,
        public ?int $product_id,
        public ?int $parent_container_id,
        public ?int $store_id,
        public ?int $opportunity_id,
        public string $availability_mode,
        public ?string $notes,
        public string $created_at,
        public string $updated_at,
        /** @var Lazy|array<int, ContainerItemData> */
        public Lazy|array $items = [],
    ) {}

    public static function fromModel(Container $container): self
    {
        return new self(
            id: $container->id,
            uuid: $container->uuid,
            name: $container->name,
            barcode: $container->barcode,
            status: $container->status->value,
            scan_mode: $container->scan_mode->value,
            is_temporary: $container->is_temporary,
            serialised_item_id: $container->serialised_item_id,
            product_id: $container->product_id,
            parent_container_id: $container->parent_container_id,
            store_id: $container->store_id,
            opportunity_id: $container->opportunity_id,
            availability_mode: $container->availabilityMode()->value,
            notes: $container->notes,
            created_at: self::formatTimestamp($container->created_at),
            updated_at: self::formatTimestamp($container->updated_at),
            items: Lazy::whenLoaded(
                'activeItems',
                $container,
                fn (): array => $container->activeItems->map(
                    fn (ContainerItem $item): ContainerItemData => ContainerItemData::fromModel($item)
                )->all(),
            ),
        );
    }
}

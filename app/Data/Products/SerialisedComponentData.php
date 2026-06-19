<?php

namespace App\Data\Products;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\SerialisedComponent;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * API/serialisation representation of a kit composition line.
 */
class SerialisedComponentData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $product_id,
        public int $component_product_id,
        public string $component_name,
        public string $quantity,
        public string $binding,
        public int $sort_order,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(SerialisedComponent $component): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $component->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $component->updated_at;

        $componentName = '';
        if ($component->relationLoaded('componentProduct') && $component->componentProduct) {
            $componentName = $component->componentProduct->name;
        }

        return new self(
            id: $component->id,
            product_id: $component->product_id,
            component_product_id: $component->component_product_id,
            component_name: $componentName,
            quantity: (string) $component->quantity,
            binding: $component->binding->value,
            sort_order: $component->sort_order,
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
        );
    }
}

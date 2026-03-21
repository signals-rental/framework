<?php

namespace App\Data\Products;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\ProductGroup;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class ProductGroupData extends Data
{
    use FormatsTimestamps;

    /**
     * @param  array<string, mixed>  $custom_fields
     * @param  array<string, mixed>|null  $icon
     * @param  array<string, mixed>|null  $parent
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public ?int $parent_id,
        public int $sort_order,
        public array $custom_fields,
        public string $created_at,
        public string $updated_at,
        public ?array $icon = null,
        public ?array $parent = null,
    ) {}

    public static function fromModel(ProductGroup $group): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $group->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $group->updated_at;

        return new self(
            id: $group->id,
            name: $group->name,
            description: $group->description ?? '',
            parent_id: $group->parent_id,
            sort_order: $group->sort_order ?? 0,
            custom_fields: $group->relationLoaded('customFieldValues') ? $group->custom_fields : [],
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
            icon: null,
            parent: $group->relationLoaded('parent') && $group->parent
                ? ['id' => $group->parent->id, 'name' => $group->parent->name]
                : null,
        );
    }
}

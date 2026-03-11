<?php

namespace App\Data\CustomFields;

use App\Models\CustomFieldGroup;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class CustomFieldGroupData extends Data
{
    /**
     * @param  list<array<string, mixed>>|null  $custom_fields
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $sort_order,
        public ?string $plugin_name,
        public string $created_at,
        public string $updated_at,
        public ?array $custom_fields = null,
    ) {}

    public static function fromModel(CustomFieldGroup $group): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $group->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $group->updated_at;

        return new self(
            id: $group->id,
            name: $group->name,
            description: $group->description,
            sort_order: $group->sort_order,
            plugin_name: $group->plugin_name,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
            custom_fields: $group->relationLoaded('customFields')
                ? $group->customFields->map(fn ($f): array => CustomFieldData::fromModel($f)->toArray())->all()
                : null,
        );
    }
}

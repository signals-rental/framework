<?php

namespace App\Data\ListValues;

use App\Models\ListValue;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class ListValueData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $id,
        public int $list_name_id,
        public string $name,
        public ?int $parent_id,
        public int $sort_order,
        public bool $is_system,
        public bool $is_active,
        public ?array $metadata,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(ListValue $value): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $value->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $value->updated_at;

        return new self(
            id: $value->id,
            list_name_id: $value->list_name_id,
            name: $value->name,
            parent_id: $value->parent_id,
            sort_order: $value->sort_order,
            is_system: $value->is_system,
            is_active: $value->is_active,
            metadata: $value->getAttribute('metadata'),
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}

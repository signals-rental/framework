<?php

namespace App\Data\ListValues;

use App\Models\ListName;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class ListNameData extends Data
{
    /**
     * @param  list<array<string, mixed>>|null  $values
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public bool $is_system,
        public bool $is_hierarchical,
        public string $created_at,
        public string $updated_at,
        public ?array $values = null,
    ) {}

    public static function fromModel(ListName $listName): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $listName->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $listName->updated_at;

        return new self(
            id: $listName->id,
            name: $listName->name,
            description: $listName->description,
            is_system: $listName->is_system,
            is_hierarchical: $listName->is_hierarchical,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
            values: $listName->relationLoaded('values')
                ? $listName->values->map(fn ($v): array => ListValueData::fromModel($v)->toArray())->all()
                : null,
        );
    }
}

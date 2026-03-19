<?php

namespace App\Data\Views;

use App\Models\CustomView;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class CustomViewData extends Data
{
    /**
     * @param  list<string>  $columns
     * @param  list<array{field: string, predicate: string, value: mixed}>  $filters
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $entity_type,
        public string $visibility,
        public ?int $user_id,
        public bool $is_default,
        public array $columns,
        public array $filters,
        public ?string $sort_column,
        public string $sort_direction,
        public int $per_page,
        public array $config,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(CustomView $model): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $model->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $model->updated_at;

        return new self(
            id: $model->id,
            name: $model->name,
            entity_type: $model->entity_type,
            visibility: $model->visibility,
            user_id: $model->user_id,
            is_default: $model->is_default,
            columns: $model->columns,
            filters: $model->filters,
            sort_column: $model->sort_column,
            sort_direction: $model->sort_direction,
            per_page: $model->per_page,
            config: $model->config,
            created_at: $createdAt->utc()->toIso8601String(),
            updated_at: $updatedAt->utc()->toIso8601String(),
        );
    }
}

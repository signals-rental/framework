<?php

namespace App\Data\Api;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\Permission\Models\Role;

class RoleData extends Data
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public bool $is_system,
        public int $sort_order,
        public array $permissions = [],
        public ?string $created_at = null,
        public ?string $updated_at = null,
    ) {}

    public static function fromModel(Role $role): self
    {
        /** @var Carbon|null $createdAt */
        $createdAt = $role->created_at;

        /** @var Carbon|null $updatedAt */
        $updatedAt = $role->updated_at;

        return new self(
            id: $role->id,
            name: $role->name,
            description: $role->description ?? null,
            is_system: (bool) ($role->is_system ?? false),
            sort_order: (int) ($role->sort_order ?? 0),
            permissions: $role->permissions->pluck('name')->toArray(),
            created_at: $createdAt?->toIso8601String(),
            updated_at: $updatedAt?->toIso8601String(),
        );
    }
}

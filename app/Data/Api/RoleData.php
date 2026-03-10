<?php

namespace App\Data\Api;

use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

class RoleData
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $is_system,
        public readonly int $sort_order,
        public readonly array $permissions = [],
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null,
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'sort_order' => $this->sort_order,
            'permissions' => $this->permissions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

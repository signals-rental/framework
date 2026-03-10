<?php

namespace App\Services;

class PermissionRegistry
{
    /**
     * @var array<string, array{label: string, description: string, group: string}>
     */
    private array $permissions = [];

    /**
     * Register a permission with metadata.
     *
     * @param  array{label: string, description: string, group: string}  $metadata
     */
    public function register(string $key, array $metadata): void
    {
        $this->permissions[$key] = $metadata;
    }

    /**
     * Register multiple permissions at once.
     *
     * @param  array<string, array{label: string, description: string, group: string}>  $permissions
     */
    public function registerMany(array $permissions): void
    {
        foreach ($permissions as $key => $metadata) {
            $this->register($key, $metadata);
        }
    }

    /**
     * Get metadata for a single permission.
     *
     * @return array{label: string, description: string, group: string}|null
     */
    public function get(string $key): ?array
    {
        return $this->permissions[$key] ?? null;
    }

    /**
     * Get all registered permissions with metadata.
     *
     * @return array<string, array{label: string, description: string, group: string}>
     */
    public function all(): array
    {
        return $this->permissions;
    }

    /**
     * Get all permission keys.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->permissions);
    }

    /**
     * Get permissions grouped by their group name.
     *
     * @return array<string, array<string, array{label: string, description: string, group: string}>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->permissions as $key => $metadata) {
            $groups[$metadata['group']][$key] = $metadata;
        }

        return $groups;
    }

    /**
     * Check if a permission is registered.
     */
    public function has(string $key): bool
    {
        return isset($this->permissions[$key]);
    }
}

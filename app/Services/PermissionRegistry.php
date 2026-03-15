<?php

namespace App\Services;

class PermissionRegistry
{
    /**
     * @var array<string, array{label: string, description: string, group: string, sub_group: string|null, layer: string, dependencies: list<string>}>
     */
    private array $permissions = [];

    /**
     * Register a permission with metadata.
     *
     * @param  array{label: string, description: string, group: string, sub_group?: string|null, layer?: string, dependencies?: list<string>}  $metadata
     */
    public function register(string $key, array $metadata): void
    {
        $this->permissions[$key] = [
            'label' => $metadata['label'],
            'description' => $metadata['description'],
            'group' => $metadata['group'],
            'sub_group' => $metadata['sub_group'] ?? null,
            'layer' => $metadata['layer'] ?? 'action',
            'dependencies' => $metadata['dependencies'] ?? [],
        ];
    }

    /**
     * Register multiple permissions at once.
     *
     * @param  array<string, array{label: string, description: string, group: string, sub_group?: string|null, layer?: string, dependencies?: list<string>}>  $permissions
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
     * @return array{label: string, description: string, group: string, sub_group: string|null, layer: string, dependencies: list<string>}|null
     */
    public function get(string $key): ?array
    {
        return $this->permissions[$key] ?? null;
    }

    /**
     * Get all registered permissions with metadata.
     *
     * @return array<string, array{label: string, description: string, group: string, sub_group: string|null, layer: string, dependencies: list<string>}>
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
     * @return array<string, array<string, array{label: string, description: string, group: string, sub_group: string|null, layer: string, dependencies: list<string>}>>
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

    /**
     * Get permissions filtered by layer.
     *
     * @return array<string, array{label: string, description: string, group: string, sub_group: string|null, layer: string, dependencies: list<string>}>
     */
    public function byLayer(string $layer): array
    {
        return array_filter($this->permissions, fn (array $meta): bool => $meta['layer'] === $layer);
    }

    /**
     * Get the recursive closure of all dependencies for a permission.
     *
     * @return list<string>
     */
    public function dependenciesFor(string $key): array
    {
        $resolved = [];
        $this->resolveDependencies($key, $resolved);

        return array_values($resolved);
    }

    /**
     * Recursively resolve dependencies, guarding against circular references.
     *
     * @param  array<string, string>  $resolved
     */
    private function resolveDependencies(string $key, array &$resolved): void
    {
        $meta = $this->permissions[$key] ?? null;

        if ($meta === null) {
            return;
        }

        foreach ($meta['dependencies'] as $dep) {
            if (isset($resolved[$dep])) {
                continue;
            }

            $resolved[$dep] = $dep;
            $this->resolveDependencies($dep, $resolved);
        }
    }
}

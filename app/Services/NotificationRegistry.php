<?php

namespace App\Services;

use App\Models\NotificationType;

class NotificationRegistry
{
    /**
     * @var array<string, array{category: string, name: string, description: string, available_channels: list<string>, default_channels: list<string>}>
     */
    private array $types = [];

    /**
     * Register a notification type.
     *
     * @param  array{category: string, name: string, description: string, available_channels: list<string>, default_channels: list<string>}  $definition
     */
    public function register(string $key, array $definition): void
    {
        $this->types[$key] = $definition;
    }

    /**
     * Register multiple notification types at once.
     *
     * @param  array<string, array{category: string, name: string, description: string, available_channels: list<string>, default_channels: list<string>}>  $types
     */
    public function registerMany(array $types): void
    {
        foreach ($types as $key => $definition) {
            $this->register($key, $definition);
        }
    }

    /**
     * Get a notification type definition by key.
     *
     * @return array{category: string, name: string, description: string, available_channels: list<string>, default_channels: list<string>}|null
     */
    public function get(string $key): ?array
    {
        return $this->types[$key] ?? null;
    }

    /**
     * Get all registered notification type definitions.
     *
     * @return array<string, array{category: string, name: string, description: string, available_channels: list<string>, default_channels: list<string>}>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * Get notification types grouped by category.
     *
     * @return array<string, array<string, array{category: string, name: string, description: string, available_channels: list<string>, default_channels: list<string>}>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->types as $key => $definition) {
            $groups[$definition['category']][$key] = $definition;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * Sync registered types to the database.
     */
    public function syncToDatabase(): void
    {
        foreach ($this->types as $key => $definition) {
            NotificationType::query()->updateOrCreate(
                ['key' => $key],
                [
                    'category' => $definition['category'],
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'available_channels' => $definition['available_channels'],
                    'default_channels' => $definition['default_channels'],
                    'source' => 'core',
                ],
            );
        }
    }

    /**
     * Check if a notification type is registered.
     */
    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }
}

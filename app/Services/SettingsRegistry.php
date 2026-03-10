<?php

namespace App\Services;

use App\Settings\SettingsDefinition;

class SettingsRegistry
{
    /** @var array<string, SettingsDefinition> */
    private array $definitions = [];

    /**
     * Register a settings definition.
     */
    public function register(SettingsDefinition $definition): void
    {
        $this->definitions[$definition->group()] = $definition;
    }

    /**
     * Get a settings definition by group name.
     */
    public function get(string $group): ?SettingsDefinition
    {
        return $this->definitions[$group] ?? null;
    }

    /**
     * Get all registered settings definitions.
     *
     * @return array<string, SettingsDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * Check if a definition exists for the given group.
     */
    public function has(string $group): bool
    {
        return isset($this->definitions[$group]);
    }

    /**
     * Get default values for a group.
     *
     * @return array<string, mixed>
     */
    public function defaults(string $group): array
    {
        $definition = $this->get($group);

        return $definition ? $definition->defaults() : [];
    }

    /**
     * Get type declarations for a group.
     *
     * @return array<string, string>
     */
    public function types(string $group): array
    {
        $definition = $this->get($group);

        return $definition ? $definition->types() : [];
    }

    /**
     * Get validation rules for a group.
     *
     * @return array<string, array<int, string|\Illuminate\Validation\Rule>>
     */
    public function rules(string $group): array
    {
        $definition = $this->get($group);

        return $definition ? $definition->rules() : [];
    }
}

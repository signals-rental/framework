<?php

namespace App\Settings;

abstract class SettingsDefinition
{
    /**
     * The settings group name (e.g. 'email', 'security').
     */
    abstract public function group(): string;

    /**
     * Default values for all settings in this group.
     *
     * @return array<string, mixed>
     */
    abstract public function defaults(): array;

    /**
     * Validation rules for settings in this group.
     *
     * @return array<string, array<int, string|\Illuminate\Validation\Rule>>
     */
    abstract public function rules(): array;

    /**
     * Type declarations for settings that need special storage handling.
     * Keys not listed default to 'string'.
     *
     * @return array<string, string>
     */
    public function types(): array
    {
        return [];
    }
}

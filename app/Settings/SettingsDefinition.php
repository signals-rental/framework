<?php

namespace App\Settings;

use App\Services\SettingsService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
     * @return array<string, array<int, string|Rule>>
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

    /**
     * Guard a pending update against group-specific invariants before it is
     * written.
     *
     * Runs after validation passes and before any value is persisted. The
     * default is a no-op so existing settings groups are unaffected. Override to
     * enforce write-time rules (e.g. immutability once dependent data exists),
     * throwing {@see ValidationException} to surface a 422
     * with field-scoped errors.
     *
     * @param  array<string, mixed>  $input  The validated, schema-filtered keys being written.
     *
     * @throws ValidationException
     */
    public function guard(array $input, SettingsService $settings): void
    {
        // No-op by default.
    }
}

<?php

namespace App\Livewire\Concerns;

use Illuminate\Validation\ValidationException;

/** @phpstan-ignore trait.unused (used by Volt components in Blade files) */
trait ReKeysCustomFieldErrors
{
    /**
     * Re-key custom field validation errors for wire:model binding.
     *
     * CustomFieldValidator throws bare keys (e.g. "test"),
     * DTO validation throws prefixed keys (e.g. "custom_fields.test").
     * This re-keys both to "{prefix}.xxx" for wire:model binding.
     *
     * @param  list<string>  $customFieldNames
     */
    protected function reKeyCustomFieldErrors(ValidationException $e, array $customFieldNames, string $prefix = 'customFieldValues'): never
    {
        $reKeyed = [];
        foreach ($e->errors() as $key => $messages) {
            if (str_starts_with($key, 'custom_fields.')) {
                $fieldName = substr($key, strlen('custom_fields.'));
                $reKeyed["{$prefix}.{$fieldName}"] = $messages;
            } elseif (in_array($key, $customFieldNames, true)) {
                $reKeyed["{$prefix}.{$key}"] = $messages;
            } else {
                $reKeyed[$key] = $messages;
            }
        }
        throw ValidationException::withMessages($reKeyed);
    }
}

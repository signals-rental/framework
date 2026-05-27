@props(['field', 'path'])

<flux:input
    type="number"
    wire:model="{{ $path }}.{{ $field['key'] }}"
    :label="$field['label']"
    :placeholder="$field['placeholder'] ?? null"
    :description="$field['help'] ?? null"
    :required="$field['required'] ?? false"
    :min="$field['min'] ?? null"
    :max="$field['max'] ?? null"
    :step="$field['step'] ?? null"
/>

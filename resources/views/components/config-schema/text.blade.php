@props(['field', 'path'])

<flux:input
    type="text"
    wire:model="{{ $path }}.{{ $field['key'] }}"
    :label="$field['label']"
    :placeholder="$field['placeholder'] ?? null"
    :description="$field['help'] ?? null"
    :required="$field['required'] ?? false"
/>

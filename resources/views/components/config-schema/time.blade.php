@props(['field', 'path'])

<flux:input
    type="time"
    wire:model="{{ $path }}.{{ $field['key'] }}"
    :label="$field['label']"
    :description="$field['help'] ?? null"
    :required="$field['required'] ?? false"
/>

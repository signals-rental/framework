@props(['field', 'path'])

{{-- Decimals are kept as strings to preserve Brick precision, so use a text input. --}}
<flux:input
    type="text"
    inputmode="decimal"
    wire:model="{{ $path }}.{{ $field['key'] }}"
    :label="$field['label']"
    :placeholder="$field['placeholder'] ?? null"
    :description="$field['help'] ?? null"
    :required="$field['required'] ?? false"
/>

@props(['field', 'path'])

{{-- Live so it can drive sibling `visible_when` visibility on the server. --}}
<flux:checkbox
    wire:model.live="{{ $path }}.{{ $field['key'] }}"
    :label="$field['label']"
    :description="$field['help'] ?? null"
/>

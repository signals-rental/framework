@props(['field', 'path'])

{{-- Live so it can drive sibling `visible_when` visibility on the server. --}}
<flux:select
    wire:model.live="{{ $path }}.{{ $field['key'] }}"
    :label="$field['label']"
    :description="$field['help'] ?? null"
>
    @foreach(($field['options'] ?? []) as $value => $label)
        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
    @endforeach
</flux:select>

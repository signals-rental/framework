@props(['field', 'path', 'values' => []])

@php
    $repeaterPath = $path.'.'.$field['key'];
    $rows = data_get($values, $field['key'], []);
    $childCount = count($field['fields'] ?? []);
    $gridClass = match (true) {
        $childCount >= 3 => 'grid-cols-3',
        $childCount === 2 => 'grid-cols-2',
        default => 'grid-cols-1',
    };
@endphp

<div class="s-field">
    <label class="s-field-label">{{ $field['label'] }}</label>
    @if(! empty($field['help']))
        <p class="s-field-help">{{ $field['help'] }}</p>
    @endif

    <div class="mt-2 flex flex-col gap-3">
        @forelse($rows as $index => $row)
            <div wire:key="repeater-{{ $repeaterPath }}-{{ $index }}" class="flex flex-col gap-3 rounded border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="flex items-center justify-between">
                    <span class="s-field-label !mb-0">Row {{ $index + 1 }}</span>
                    <div class="flex items-center gap-1">
                        <flux:button type="button" variant="ghost" size="xs" icon="chevron-up" wire:click="moveRow('{{ $repeaterPath }}', {{ $index }}, -1)" :disabled="$index === 0" />
                        <flux:button type="button" variant="ghost" size="xs" icon="chevron-down" wire:click="moveRow('{{ $repeaterPath }}', {{ $index }}, 1)" :disabled="$index === count($rows) - 1" />
                        <flux:button type="button" variant="ghost" size="xs" icon="x-mark" wire:click="removeRow('{{ $repeaterPath }}', {{ $index }})" />
                    </div>
                </div>
                <div class="grid {{ $gridClass }} gap-3">
                    @foreach(($field['fields'] ?? []) as $child)
                        <x-config-schema.field :field="$child" :path="$repeaterPath.'.'.$index" :values="$row ?? []" />
                    @endforeach
                </div>
            </div>
        @empty
            <p class="s-field-help text-[var(--text-muted)]">No rows yet — add one to get started.</p>
        @endforelse
    </div>

    <flux:button type="button" variant="ghost" size="sm" icon="plus" class="mt-3 self-start" wire:click="addRow('{{ $repeaterPath }}')">
        Add row
    </flux:button>
</div>

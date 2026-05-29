@php
    /** @var string $model — the Livewire array property holding the selected abilities */
    /** @var array<string, array{label: string, abilities: array<string, string>}> $groups */
    $allKeys = collect($groups)->flatMap(fn ($group) => array_keys($group['abilities']))->values()->all();
@endphp

<div>
    <div class="mb-2 flex items-center justify-between">
        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Abilities</label>
        <button type="button" class="s-btn s-btn-ghost s-btn-sm"
                x-on:click="
                    const all = @js($allKeys);
                    $wire.{{ $model }} = ($wire.{{ $model }} ?? []).length >= all.length ? [] : [...all];
                ">
            <span x-text="($wire.{{ $model }} ?? []).length >= {{ count($allKeys) }} ? 'Clear all' : 'Select all'">Select all</span>
        </button>
    </div>

    <div class="space-y-1.5 max-h-80 overflow-y-auto pr-1">
        @foreach($groups as $groupKey => $group)
            @php $keys = array_keys($group['abilities']); @endphp
            <div class="rounded-md border border-zinc-200 dark:border-zinc-700"
                 wire:key="{{ $model }}-group-{{ $groupKey }}"
                 x-data="{
                     open: false,
                     keys: @js($keys),
                     get selectedCount() { const s = $wire.{{ $model }} ?? []; return this.keys.filter(k => s.includes(k)).length; },
                     get allSelected() { return this.selectedCount === this.keys.length; },
                     get someSelected() { return this.selectedCount > 0 && this.selectedCount < this.keys.length; },
                     toggleGroup() {
                         const s = new Set($wire.{{ $model }} ?? []);
                         if (this.allSelected) { this.keys.forEach(k => s.delete(k)); }
                         else { this.keys.forEach(k => s.add(k)); }
                         $wire.{{ $model }} = [...s];
                     },
                 }">
                <div class="flex items-center gap-2 px-3 py-2">
                    <button type="button" class="shrink-0" x-on:click.stop="toggleGroup()" title="Select all in group">
                        <x-signals.checkbox x-bind:class="{ 'checked': allSelected, 'opacity-40': someSelected }" />
                    </button>
                    <button type="button" class="flex flex-1 items-center justify-between gap-2 text-left" x-on:click="open = !open">
                        <span class="text-sm font-medium">{{ $group['label'] }}</span>
                        <span class="flex items-center gap-2">
                            <span class="text-xs text-zinc-400 font-mono" x-text="selectedCount + '/' + keys.length">0/{{ count($keys) }}</span>
                            <flux:icon.chevron-right class="w-4 h-4 text-zinc-400 transition-transform" x-bind:class="open && 'rotate-90'" />
                        </span>
                    </button>
                </div>
                <div x-show="open" class="border-t border-zinc-100 dark:border-zinc-800 px-3 py-2 space-y-2">
                    @foreach($group['abilities'] as $ability => $label)
                        <label class="flex items-center gap-2 cursor-pointer" wire:key="{{ $model }}-{{ $ability }}"
                               x-data="{ checked: ($wire.{{ $model }} ?? []).includes('{{ $ability }}') }"
                               x-init="$watch('$wire.{{ $model }}', value => checked = (value ?? []).includes('{{ $ability }}'))">
                            <input type="checkbox" wire:model="{{ $model }}" value="{{ $ability }}" class="hidden" x-on:change="checked = $el.checked" />
                            <x-signals.checkbox x-bind:class="checked && 'checked'" />
                            <span class="text-sm">{{ $label }}</span>
                            <span class="text-xs text-zinc-400 font-mono">{{ $ability }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

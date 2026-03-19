@if(count($selected) === 2)
    <button
        x-on:click="Livewire.dispatch('open-merge-modal', { memberA: {{ $selected[0] ?? 0 }}, memberB: {{ $selected[1] ?? 0 }} })"
        class="s-bulk-btn"
    >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 16v6"/><path d="M19 19h-6"/><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h4"/><circle cx="18" cy="7" r="3"/></svg>
        Merge
    </button>
@endif

<button
    x-on:click="$dispatch('open-modal', 'archive-selected')"
    class="s-bulk-btn s-bulk-btn-danger"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 8-2 13H5L3 8"/><path d="M7 8V6a4 4 0 0 1 4-4h2a4 4 0 0 1 4 4v2"/><path d="M1 8h22"/><path d="M10 12v6"/><path d="M14 12v6"/></svg>
    Archive Selected
</button>

<x-signals.modal name="archive-selected" title="Archive Members" size="sm">
    <p>Are you sure you want to archive {{ count($selected) }} members? They can be restored later.</p>

    <x-slot:footer>
        <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'archive-selected')">Cancel</button>
        <button class="s-btn s-btn-sm s-btn-danger" type="button" wire:click="$parent.archiveSelected({{ json_encode($selected) }})" x-on:click="$dispatch('close-modal', 'archive-selected'); $wire.clearSelection()">Archive</button>
    </x-slot:footer>
</x-signals.modal>

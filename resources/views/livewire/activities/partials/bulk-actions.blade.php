<button
    type="button"
    wire:click="$parent.bulkComplete({{ json_encode($selected) }})"
    x-on:click="$wire.clearSelection()"
    class="s-bulk-btn"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    Complete
</button>

<button
    type="button"
    x-on:click="$dispatch('open-modal', 'delete-selected-activities')"
    class="s-bulk-btn s-bulk-btn-danger"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
    Delete Selected
</button>

<x-signals.modal name="delete-selected-activities" title="Delete Activities" size="sm">
    <p>Are you sure you want to delete {{ count($selected) }} {{ Str::plural('activity', count($selected)) }}? This cannot be undone.</p>

    <x-slot:footer>
        <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'delete-selected-activities')">Cancel</button>
        <button class="s-btn s-btn-sm s-btn-danger" type="button" wire:click="$parent.bulkDelete({{ json_encode($selected) }})" x-on:click="$dispatch('close-modal', 'delete-selected-activities'); $wire.clearSelection()">Delete</button>
    </x-slot:footer>
</x-signals.modal>

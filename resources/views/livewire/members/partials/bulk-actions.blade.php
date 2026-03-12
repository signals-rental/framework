<button
    x-on:click="$dispatch('open-modal', 'delete-selected')"
    class="s-bulk-btn s-bulk-btn-danger"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
    Delete Selected
</button>

<x-signals.modal name="delete-selected" title="Delete Members" size="sm">
    <p>Are you sure you want to delete {{ count($selected) }} members? This action cannot be undone.</p>

    <x-slot:footer>
        <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'delete-selected')">Cancel</button>
        <button class="s-btn s-btn-sm s-btn-danger" type="button" wire:click="$parent.deleteSelected({{ json_encode($selected) }})" x-on:click="$dispatch('close-modal', 'delete-selected'); $wire.clearSelection()">Delete</button>
    </x-slot:footer>
</x-signals.modal>

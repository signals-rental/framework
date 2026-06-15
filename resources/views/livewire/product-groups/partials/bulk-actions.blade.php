<button
    x-on:click="$dispatch('open-modal', 'delete-selected-groups')"
    class="s-bulk-btn s-bulk-btn-danger"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 8-2 13H5L3 8"/><path d="M7 8V6a4 4 0 0 1 4-4h2a4 4 0 0 1 4 4v2"/><path d="M1 8h22"/><path d="M10 12v6"/><path d="M14 12v6"/></svg>
    {{ __('Delete Selected') }}
</button>

<x-signals.modal name="delete-selected-groups" title="Delete Product Groups" size="sm">
    <p>{{ __('Are you sure you want to delete :count product groups? This action cannot be undone.', ['count' => count($selected)]) }}</p>

    <x-slot:footer>
        <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'delete-selected-groups')">{{ __('Cancel') }}</button>
        <button class="s-btn s-btn-sm s-btn-danger" type="button" wire:click="$parent.deleteSelected({{ json_encode($selected) }})" x-on:click="$dispatch('close-modal', 'delete-selected-groups'); $wire.clearSelection()">{{ __('Delete') }}</button>
    </x-slot:footer>
</x-signals.modal>

<a href="{{ route('stock-levels.show', $item) }}" wire:navigate class="s-dropdown-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
    View
</a>
<a href="{{ route('stock-levels.edit', $item) }}" wire:navigate class="s-dropdown-item" style="text-decoration: none;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
    Edit
</a>
<div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
<button
    x-on:click="open = false; $dispatch('open-modal', 'delete-stock-level-{{ $item->id }}')"
    class="s-dropdown-item"
    style="color: var(--red); width: 100%;"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="m21 8-2 13H5L3 8"/><path d="M7 8V6a4 4 0 0 1 4-4h2a4 4 0 0 1 4 4v2"/><path d="M1 8h22"/><path d="M10 12v6"/><path d="M14 12v6"/></svg>
    Delete
</button>

<x-signals.modal name="delete-stock-level-{{ $item->id }}" title="Delete Stock Level" size="sm">
    <p>Are you sure you want to delete <strong>{{ $item->item_name ?? $item->product?->name ?? 'this stock level' }}</strong>? This cannot be undone.</p>

    <x-slot:footer>
        <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'delete-stock-level-{{ $item->id }}')">Cancel</button>
        <button class="s-btn s-btn-sm s-btn-danger" type="button" wire:click="$parent.deleteStockLevel({{ $item->id }})" x-on:click="$dispatch('close-modal', 'delete-stock-level-{{ $item->id }}')">Delete</button>
    </x-slot:footer>
</x-signals.modal>

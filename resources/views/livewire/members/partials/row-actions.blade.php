<a href="{{ route('members.show', $item) }}" wire:navigate class="s-dropdown-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
    View
</a>
<a href="{{ route('members.edit', $item) }}" wire:navigate class="s-dropdown-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
    Edit
</a>
<div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
<button
    x-on:click="open = false; $dispatch('open-modal', 'delete-member-{{ $item->id }}')"
    class="s-dropdown-item"
    style="color: var(--red); width: 100%;"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
    Delete
</button>

<x-signals.modal name="delete-member-{{ $item->id }}" title="Delete Member" size="sm">
    <p>Are you sure you want to delete this member? This action cannot be undone.</p>

    <x-slot:footer>
        <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'delete-member-{{ $item->id }}')">Cancel</button>
        <button class="s-btn s-btn-sm s-btn-danger" type="button" wire:click="$parent.deleteMember({{ $item->id }})" x-on:click="$dispatch('close-modal', 'delete-member-{{ $item->id }}')">Delete</button>
    </x-slot:footer>
</x-signals.modal>

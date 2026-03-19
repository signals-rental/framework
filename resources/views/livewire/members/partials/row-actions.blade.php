<a href="{{ route('members.show', $item) }}" wire:navigate class="s-dropdown-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
    View
</a>
<a href="{{ route('members.edit', $item) }}" wire:navigate class="s-dropdown-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
    Edit
</a>
<div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
@if($item->trashed())
    <button
        x-on:click="open = false; $wire.$parent.restoreMember({{ $item->id }})"
        class="s-dropdown-item"
        style="color: var(--green); width: 100%;"
    >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Restore
    </button>
@else
    <button
        x-on:click="open = false; $dispatch('open-modal', 'archive-member-{{ $item->id }}')"
        class="s-dropdown-item"
        style="color: var(--red); width: 100%;"
    >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="m21 8-2 13H5L3 8"/><path d="M7 8V6a4 4 0 0 1 4-4h2a4 4 0 0 1 4 4v2"/><path d="M1 8h22"/><path d="M10 12v6"/><path d="M14 12v6"/></svg>
        Archive
    </button>

    <x-signals.modal name="archive-member-{{ $item->id }}" title="Archive Member" size="sm">
        <p>Are you sure you want to archive <strong>{{ $item->name }}</strong>? The member can be restored later.</p>

        <x-slot:footer>
            <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'archive-member-{{ $item->id }}')">Cancel</button>
            <button class="s-btn s-btn-sm s-btn-danger" type="button" wire:click="$parent.archiveMember({{ $item->id }})" x-on:click="$dispatch('close-modal', 'archive-member-{{ $item->id }}')">Archive</button>
        </x-slot:footer>
    </x-signals.modal>
@endif

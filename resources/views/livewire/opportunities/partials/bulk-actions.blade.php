{{--
    The data-table renders this bulk view with only `$selected` in scope — the
    parent's $archiveFilter is not visible here, so the Restore button cannot be
    conditionally shown for the archived view alone. Both actions are therefore
    rendered unconditionally: this is safe because RestoreOpportunity early-returns
    when the row is not trashed (a no-op on active rows), and DeleteOpportunity is
    idempotent on already-archived rows. Opportunities are not a dup-prone entity,
    so there is deliberately NO merge action.
--}}
<button
    x-on:click="$dispatch('open-modal', 'archive-selected')"
    class="s-bulk-btn s-bulk-btn-danger"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 8-2 13H5L3 8"/><path d="M7 8V6a4 4 0 0 1 4-4h2a4 4 0 0 1 4 4v2"/><path d="M1 8h22"/><path d="M10 12v6"/><path d="M14 12v6"/></svg>
    Archive Selected
</button>

<button
    x-on:click="$dispatch('open-modal', 'restore-selected')"
    class="s-bulk-btn"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
    Restore Selected
</button>

<x-signals.modal name="archive-selected" title="Archive Opportunities" size="sm">
    <p>Are you sure you want to archive {{ count($selected) }} opportunities? They can be restored later.</p>

    <x-slot:footer>
        <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'archive-selected')">Cancel</button>
        <button class="s-btn s-btn-sm s-btn-danger" type="button" wire:click="$parent.archiveSelected({{ json_encode($selected) }})" x-on:click="$dispatch('close-modal', 'archive-selected'); $wire.clearSelection()">Archive</button>
    </x-slot:footer>
</x-signals.modal>

<x-signals.modal name="restore-selected" title="Restore Opportunities" size="sm">
    <p>Are you sure you want to restore {{ count($selected) }} opportunities?</p>

    <x-slot:footer>
        <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'restore-selected')">Cancel</button>
        <button class="s-btn s-btn-sm s-btn-accent" type="button" wire:click="$parent.restoreSelected({{ json_encode($selected) }})" x-on:click="$dispatch('close-modal', 'restore-selected'); $wire.clearSelection()">Restore</button>
    </x-slot:footer>
</x-signals.modal>

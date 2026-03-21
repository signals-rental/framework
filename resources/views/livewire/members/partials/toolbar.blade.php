<x-signals.column-toggle />
<x-signals.export-button />
<div x-data="{ open: false }" class="relative inline-flex">
    <button x-on:click.stop="open = !open" class="s-btn s-btn-sm s-btn-accent">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
        Add Member
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3 ml-1"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <div
        x-show="open"
        x-on:click.outside="open = false"
        x-transition
        x-cloak
        class="s-dropdown"
        style="position: absolute; top: 100%; right: 0; left: auto; margin-top: 4px; z-index: 50; min-width: 180px;"
    >
        <a href="{{ route('members.create', ['type' => 'organisation']) }}" wire:navigate class="s-dropdown-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><path d="M3 21h18M3 7v14M21 7v14M6 11h2M6 15h2M10 11h2M10 15h2M14 11h2M14 15h2M18 11h2M18 15h2M8 7V3h8v4"/></svg>
            Add Organisation
        </a>
        <a href="{{ route('members.create', ['type' => 'contact']) }}" wire:navigate class="s-dropdown-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Add Contact
        </a>
        <a href="{{ route('members.create', ['type' => 'venue']) }}" wire:navigate class="s-dropdown-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Add Venue
        </a>
    </div>
</div>

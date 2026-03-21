<div class="flex items-center gap-1">
    @if(!$row->completed)
        <button wire:click="$parent.completeActivity({{ $row->id }})" class="s-btn s-btn-xs s-btn-ghost" title="Complete">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><polyline points="20 6 9 17 4 12"/></svg>
        </button>
    @endif
    <a href="{{ route('activities.edit', $row->id) }}" wire:navigate class="s-btn s-btn-xs s-btn-ghost" title="Edit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    </a>
    <button wire:click="$parent.deleteActivity({{ $row->id }})" wire:confirm="Are you sure you want to delete this activity?" class="s-btn s-btn-xs s-btn-ghost s-btn-danger" title="Delete">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
    </button>
</div>

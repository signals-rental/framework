<div x-data="{ colsOpen: false }" x-ref="colsWrap" class="relative inline-flex">
    <button x-on:click.stop="colsOpen = !colsOpen" class="s-btn s-btn-sm">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/></svg>
        Columns
    </button>
    <div
        x-show="colsOpen"
        x-on:click.outside="colsOpen = false"
        x-cloak
        style="position: fixed; z-index: 9999;"
        x-ref="colsMenu"
        x-init="$watch('colsOpen', value => {
            if (value) {
                $nextTick(() => {
                    const wrap = $refs.colsWrap;
                    const rect = wrap.getBoundingClientRect();
                    $refs.colsMenu.style.top = rect.bottom + 4 + 'px';
                    $refs.colsMenu.style.right = (window.innerWidth - rect.right) + 'px';
                    $refs.colsMenu.style.left = 'auto';
                });
            }
        })"
    >
        <div class="s-dropdown" style="position: static; min-width: 200px; max-height: 320px; overflow-y: auto;">
            <div class="s-dropdown-group">Toggle Columns</div>
            @foreach($this->toggleableColumns as $col)
                <button
                    wire:click="toggleColumn('{{ $col['key'] }}')"
                    class="s-dropdown-item"
                    style="width: 100%; text-align: left;"
                >
                    <span style="width: 14px; height: 14px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; border: 1.5px solid {{ $col['visible'] ? 'var(--green)' : 'var(--card-border)' }}; border-radius: 3px; background: {{ $col['visible'] ? 'var(--green)' : 'transparent' }};">
                        @if($col['visible'])
                            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" style="width: 10px; height: 10px;"><polyline points="20 6 9 17 4 12"/></svg>
                        @endif
                    </span>
                    {{ $col['label'] }}
                </button>
            @endforeach
        </div>
    </div>
</div>
<a href="#" class="s-btn s-btn-sm">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Export
</a>
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

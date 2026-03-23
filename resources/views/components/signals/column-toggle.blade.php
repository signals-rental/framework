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
                    <span style="width: 14px; height: 14px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; border: 1.5px solid {{ $col['visible'] ? 'var(--blue)' : 'var(--card-border)' }}; border-radius: 3px; background: {{ $col['visible'] ? 'var(--blue)' : 'transparent' }};">
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

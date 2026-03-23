<div x-data="{ open: false }" x-ref="viewWrap" class="relative">
    {{-- Split-button style trigger --}}
    <div class="s-btn-split">
        <button class="s-btn s-btn-sm s-btn-split-main" type="button" wire:click="switchView({{ $viewId ?? 'null' }})">
            {{ $viewId === null ? 'Default' : $activeViewName }}
        </button>
        <button class="s-btn s-btn-sm s-btn-split-trigger" type="button" x-on:click="open = !open">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
    </div>

    {{-- Dropdown menu using s-dropdown pattern --}}
    <div x-show="open" x-on:click.outside="open = false" x-cloak
        style="position: fixed; z-index: 9999;"
        x-ref="viewMenu"
        x-init="$watch('open', value => {
            if (value) {
                $nextTick(() => {
                    const wrap = $refs.viewWrap;
                    const rect = wrap.getBoundingClientRect();
                    $refs.viewMenu.style.top = rect.bottom + 4 + 'px';
                    $refs.viewMenu.style.left = rect.left + 'px';
                });
            }
        })"
    >
        <div class="s-dropdown" style="position: static; min-width: 220px;">
            @foreach(['system' => 'System', 'shared' => 'Shared', 'personal' => 'My Custom Views'] as $group => $label)
                @if(isset($availableViews[$group]))
                    <div class="s-dropdown-group">{{ $label }}</div>
                    @foreach($availableViews[$group] as $viewOption)
                        <button
                            wire:click="switchView({{ $viewOption['id'] }})"
                            x-on:click="open = false"
                            class="s-dropdown-item {{ $viewId === $viewOption['id'] ? 'font-semibold' : '' }}"
                            style="width: 100%; text-align: left;{{ $viewId === $viewOption['id'] ? ' color: var(--blue);' : '' }}">
                            @if($viewId === $viewOption['id'])
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 12px; height: 12px; flex-shrink: 0;"><polyline points="20 6 9 17 4 12"/></svg>
                            @else
                                <span style="width: 12px; flex-shrink: 0;"></span>
                            @endif
                            {{ $viewOption['name'] }}
                            @if($viewOption['is_default'] ?? false)
                                <span style="font-size: 10px; opacity: 0.5; margin-left: auto;">default</span>
                            @endif
                        </button>
                    @endforeach
                @endif
            @endforeach

            @if($viewId !== null)
                <div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
                <button wire:click="setDefaultView" x-on:click="open = false" class="s-dropdown-item" style="width: 100%; text-align: left;">
                    Set as my default
                </button>
                <button x-on:click="open = false; $dispatch('open-view-builder', { viewId: {{ $viewId }} })" class="s-dropdown-item" style="width: 100%; text-align: left;">
                    Edit custom view
                </button>
            @endif

            <div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
            <button x-on:click="open = false; $dispatch('open-view-builder', { viewId: null })" class="s-dropdown-item" style="width: 100%; text-align: left; color: var(--blue);">
                + New custom view
            </button>
        </div>
    </div>
</div>

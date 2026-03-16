<div x-data="{ open: false }" class="relative inline-flex flex-shrink-0">
    <button x-on:click.stop="open = !open" class="s-btn-ghost s-btn-xs s-btn-icon">
        <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
    </button>
    <div
        x-show="open"
        x-on:click.outside="open = false"
        x-transition
        x-cloak
        class="s-dropdown"
        style="position: fixed; z-index: 9999;"
        x-ref="dropdown"
        x-init="$watch('open', value => {
            if (value) {
                $nextTick(() => {
                    const btn = $el.previousElementSibling;
                    const rect = btn.getBoundingClientRect();
                    $refs.dropdown.style.top = rect.bottom + 4 + 'px';
                    $refs.dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                    $refs.dropdown.style.left = 'auto';
                });
            }
        })"
    >
        <a href="{{ $editRoute }}" wire:navigate class="s-dropdown-item" style="text-decoration: none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
            Edit
        </a>
        <div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
        <button x-on:click="open = false" wire:click="{{ $deleteAction }}" wire:confirm="{{ $deleteConfirm }}" class="s-dropdown-item" style="color: var(--red); width: 100%;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
            Delete
        </button>
    </div>
</div>

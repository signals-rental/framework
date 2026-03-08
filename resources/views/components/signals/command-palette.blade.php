@props([
    'placeholder' => 'Type a command or search...',
])

<div
    {{ $attributes->merge(['class' => '']) }}
    x-data="{
        open: false,
        activeIndex: 0,
        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    this.open = !this.open;
                    if (this.open) {
                        this.$nextTick(() => this.$refs.searchInput?.focus());
                    }
                }
            });
        },
    }"
    x-on:keydown.escape.window="open = false"
>
    <template x-teleport="body">
        <div class="s-command-backdrop" x-show="open" x-cloak x-transition.opacity x-on:click.self="open = false">
            <div class="s-command-palette" x-trap.noscroll="open">
                <div class="s-command-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input
                        x-ref="searchInput"
                        type="text"
                        placeholder="{{ $placeholder }}"
                        {{ $attributes->whereStartsWith('wire:') }}
                    >
                </div>
                <div class="s-command-results">
                    {{ $slot }}
                </div>
                <div class="s-command-footer">
                    <span><span class="s-kbd">↑↓</span> Navigate</span>
                    <span><span class="s-kbd">↵</span> Select</span>
                    <span><span class="s-kbd">Esc</span> Close</span>
                </div>
            </div>
        </div>
    </template>
</div>

@props([
    'images' => [],
    'columns' => 3,
])

<div
    {{ $attributes }}
    x-data="{
        images: @js($images),
        open: false,
        current: 0,
        show(index) { this.current = index; this.open = true; },
        next() { this.current = (this.current + 1) % this.images.length; },
        prev() { this.current = (this.current - 1 + this.images.length) % this.images.length; },
        onKey(e) { if (!this.open) return; if (e.key === 'ArrowRight') this.next(); else if (e.key === 'ArrowLeft') this.prev(); else if (e.key === 'Escape') this.open = false; }
    }"
    x-on:keydown.window="onKey($event)"
>
    <div class="s-gallery-grid" data-cols="{{ $columns }}">
        <template x-for="(img, i) in images" :key="i">
            <div class="s-gallery-item" x-on:click="show(i)">
                <img :src="img.src" :alt="img.alt || ''">
            </div>
        </template>
    </div>

    {{-- Lightbox --}}
    <template x-teleport="body">
        <div
            class="s-gallery-lightbox"
            x-show="open"
            x-cloak
            x-transition.opacity
            x-on:click.self="open = false"
        >
            <button class="s-gallery-lightbox-close" type="button" x-on:click="open = false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <button class="s-gallery-lightbox-nav prev" type="button" x-on:click="prev()" x-show="images.length > 1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <img class="s-gallery-lightbox-img" :src="images[current]?.src" :alt="images[current]?.alt || ''">
            <button class="s-gallery-lightbox-nav next" type="button" x-on:click="next()" x-show="images.length > 1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
            <div class="s-gallery-lightbox-caption" x-text="images[current]?.caption || ''" x-show="images[current]?.caption"></div>
        </div>
    </template>
</div>

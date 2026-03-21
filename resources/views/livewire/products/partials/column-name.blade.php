@php
    $iconSrc = null;
    if ($item->icon_thumb_url) {
        try {
            $iconSrc = app(\App\Services\FileService::class)->signedUrl($item->icon_thumb_url);
        } catch (\Throwable) {}
    }
@endphp
<div class="flex items-center gap-2.5">
    @if($iconSrc)
        <div class="flex shrink-0 items-center justify-center size-8 overflow-hidden rounded" style="background: var(--s-subtle);">
            <img src="{{ $iconSrc }}" alt="" class="size-full object-cover" />
        </div>
    @else
        <div class="flex shrink-0 items-center justify-center size-8 rounded" style="background: var(--s-subtle);">
            <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" class="size-4"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
    @endif
    <a href="{{ route('products.show', $item) }}" wire:navigate class="font-semibold" style="color: var(--blue); text-decoration: none;">
        {{ $item->name }}
    </a>
</div>

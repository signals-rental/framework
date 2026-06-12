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
            <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" class="size-4"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        </div>
    @endif
    <a href="{{ route('products.index', ['filters' => ['product_group_id' => $item->id]]) }}" wire:navigate class="font-semibold" style="color: var(--blue); text-decoration: none;">
        {{ $item->name }}
    </a>
</div>

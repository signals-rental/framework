@php
    $headerIconSrc = null;
    $headerIconFullSrc = null;
    if ($product->icon_thumb_url) {
        try {
            $headerIconSrc = app(\App\Services\FileService::class)->signedUrl($product->icon_thumb_url);
        } catch (\Throwable) {}
    }
    if ($product->icon_url) {
        try {
            $headerIconFullSrc = app(\App\Services\FileService::class)->signedUrl($product->icon_url);
        } catch (\Throwable) {}
    }
    $headerWords = preg_split('/\s+/', trim($product->name));
    $headerInitials = mb_strtoupper(mb_substr($headerWords[0] ?? '', 0, 1) . mb_substr($headerWords[1] ?? '', 0, 1));
@endphp
<x-signals.page-header :title="$product->name">
    <x-slot:icon>
        <div class="flex size-11 items-center justify-center overflow-hidden rounded-lg border border-[var(--card-border)] bg-white shadow-sm">
            @if($headerIconSrc)
                <a href="{{ $headerIconFullSrc ?? $headerIconSrc }}" target="_blank" class="block">
                    <img src="{{ $headerIconSrc }}" alt="{{ $product->name }}" class="size-full object-cover" />
                </a>
            @else
                <span class="text-sm font-bold text-[var(--text-muted)]" style="font-family: var(--font-display);">{{ $headerInitials }}</span>
            @endif
        </div>
    </x-slot:icon>
    <x-slot:breadcrumbs>
        <a href="{{ route('products.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Products</a>
        <span class="mx-1 text-[var(--text-muted)]">/</span>
        @if(isset($subpage))
            <a href="{{ route('products.show', $product) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $product->name }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $subpage }}</span>
        @else
            <span>{{ $product->name }}</span>
        @endif
    </x-slot:breadcrumbs>
    <x-slot:meta>
        @php
            $typeBadgeClass = match($product->product_type) {
                \App\Enums\ProductType::Rental => 's-badge-blue',
                \App\Enums\ProductType::Sale => 's-badge-green',
                \App\Enums\ProductType::Service => 's-badge-amber',
                \App\Enums\ProductType::LossAndDamage => 's-badge-red',
                default => 's-badge-zinc',
            };
        @endphp
        <span class="s-badge {{ $typeBadgeClass }}" style="display: inline-flex; align-items: center; gap: 4px;">
            @if($product->product_type === \App\Enums\ProductType::Rental)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
            @elseif($product->product_type === \App\Enums\ProductType::Sale)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            @elseif($product->product_type === \App\Enums\ProductType::Service)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            @elseif($product->product_type === \App\Enums\ProductType::LossAndDamage)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            @endif
            {{ $product->product_type->label() }}
        </span>
        @if($product->is_active)
            <span class="s-badge s-badge-green"><span class="s-badge-dot"></span> Active</span>
        @else
            <span class="s-badge s-badge-zinc"><span class="s-badge-dot"></span> Inactive</span>
        @endif
        @if($product->trashed())
            <span class="s-badge s-badge-red"><span class="s-badge-dot"></span> Archived</span>
        @endif
    </x-slot:meta>
    <x-slot:actions>
        <a href="{{ route('products.edit', $product) }}" wire:navigate class="s-btn s-btn-sm s-btn-accent">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
            Edit
        </a>
        <x-signals.split-button label="New" size="sm">
            <a href="{{ route('products.stock', $product) }}" wire:navigate class="s-dropdown-item" style="text-decoration: none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                Stock Level
            </a>
            <div class="s-dropdown-item" style="opacity: 0.5; cursor: default;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.735-3.684-1.757 1.757a4.5 4.5 0 0 1-6.364 0 4.5 4.5 0 0 1 0-6.364l4.5-4.5a4.5 4.5 0 0 1 6.364 6.364l-1.757 1.757"/></svg>
                Accessory
            </div>
            <div class="s-dropdown-divider"></div>
            <div class="s-dropdown-item" style="opacity: 0.5; cursor: default;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                Merge with...
            </div>
        </x-signals.split-button>
    </x-slot:actions>
</x-signals.page-header>

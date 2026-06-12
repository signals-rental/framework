@php($count = $item->products_count ?? 0)
@if($count > 0)
    <a href="{{ route('products.index', ['filters' => ['product_group_id' => $item->id]]) }}" wire:navigate class="s-chip" title="View products in this group">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        Products <span class="s-chip-count">{{ $count }}</span>
    </a>
@else
    <span class="s-chip" style="opacity: 0.5;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        Products <span class="s-chip-count">0</span>
    </span>
@endif

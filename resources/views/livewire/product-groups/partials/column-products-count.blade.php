@if(($item->products_count ?? 0) > 0)
    <a href="{{ route('products.index', ['filters' => ['product_group_id' => $item->id]]) }}" wire:navigate style="color: var(--blue); text-decoration: none; font-weight: 600;">{{ $item->products_count }}</a>
@else
    <span class="text-[var(--text-muted)]">0</span>
@endif

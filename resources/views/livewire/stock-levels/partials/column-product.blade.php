@if($item->product)
    <a href="{{ route('products.show', $item->product) }}" wire:navigate class="font-semibold" style="color: var(--blue); text-decoration: none;">
        {{ $item->product->name }}
    </a>
@else
    <span class="text-[var(--text-muted)]">—</span>
@endif

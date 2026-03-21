<a href="{{ route('products.show', $item) }}" wire:navigate class="font-semibold" style="color: var(--blue); text-decoration: none;">
    {{ $item->name }}
</a>

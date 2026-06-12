<a href="{{ route('stock-levels.show', $item) }}" wire:navigate class="font-semibold" style="color: var(--blue); text-decoration: none;">
    {{ $item->item_name ?: 'Stock level #' . $item->id }}
</a>

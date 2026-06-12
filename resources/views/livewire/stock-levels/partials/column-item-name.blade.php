@if($item->item_name)
    <a href="{{ route('stock-levels.show', $item) }}" wire:navigate class="font-semibold" style="color: var(--blue); text-decoration: none;">
        {{ $item->item_name }}
    </a>
@else
    <span class="text-[var(--text-muted)]">—</span>
@endif

@php($label = $item->product?->name ?? $item->item_name)
@if($label)
    <a href="{{ route('stock-levels.show', $item) }}" wire:navigate class="font-semibold" style="color: var(--blue-ink); text-decoration: none;">{{ $label }}</a>
@else
    <span class="text-[var(--text-muted)]">—</span>
@endif

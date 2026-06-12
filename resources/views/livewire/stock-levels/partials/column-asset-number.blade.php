@if($item->asset_number)
    <a href="{{ route('stock-levels.show', $item) }}" wire:navigate style="font-family: var(--font-mono); font-size: 11px; color: var(--blue-ink); text-decoration: none;">
        {{ $item->asset_number }}
    </a>
@else
    <span class="text-[var(--text-muted)]">—</span>
@endif

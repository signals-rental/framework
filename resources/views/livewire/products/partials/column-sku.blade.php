@if($item->sku)
    <span style="font-family: var(--font-mono); font-size: 11px;">{{ $item->sku }}</span>
@else
    <span class="text-[var(--text-muted)]">—</span>
@endif

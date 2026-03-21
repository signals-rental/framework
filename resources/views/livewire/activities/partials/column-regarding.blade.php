@if($item->regarding)
    <span class="text-sm">{{ $item->regarding->name ?? '—' }}</span>
    <span class="text-xs text-[var(--text-muted)]">{{ $item->regarding_type }}</span>
@else
    <span class="text-sm text-[var(--text-muted)]">—</span>
@endif

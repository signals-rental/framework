@if($row->regarding)
    <span class="text-sm">{{ $row->regarding->name ?? '—' }}</span>
    <span class="text-xs text-[var(--text-muted)]">{{ $row->regarding_type }}</span>
@else
    <span class="text-sm text-[var(--text-muted)]">—</span>
@endif

@if($item->store)
    <span class="s-badge s-badge-zinc">{{ $item->store->name }}</span>
@else
    <span class="text-[var(--text-muted)]">—</span>
@endif

@if($item->parent)
    {{ $item->parent->name }}
@else
    <span class="text-[var(--text-muted)]">—</span>
@endif

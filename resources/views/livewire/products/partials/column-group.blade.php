@if($item->productGroup)
    <button type="button" wire:click="applyFilter('product_group_id', '{{ $item->product_group_id }}')" class="font-medium" style="color: var(--blue); text-decoration: none;">{{ $item->productGroup->name }}</button>
@else
    <span class="text-[var(--text-muted)]">—</span>
@endif

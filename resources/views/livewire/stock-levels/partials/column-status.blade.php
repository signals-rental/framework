@php
    $held = (float) $item->quantity_held;
    $allocated = (float) $item->quantity_allocated;
    $unavailable = (float) $item->quantity_unavailable;
    $available = $held - $allocated - $unavailable;
@endphp
@if($unavailable > 0 && $available <= 0)
    <span class="s-status s-status-red"><span class="s-status-dot"></span> Quarantined</span>
@elseif($allocated >= $held)
    <span class="s-status s-status-amber"><span class="s-status-dot"></span> Fully Allocated</span>
@elseif($available > 0)
    <span class="s-status s-status-green"><span class="s-status-dot"></span> Available</span>
@else
    <span class="s-status s-status-zinc"><span class="s-status-dot"></span> None</span>
@endif

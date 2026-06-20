@php
    $status = $item->statusEnum();
    $statusStatusClass = $status->isClosed() ? 's-status-zinc' : 's-status-green';
@endphp
<span class="s-status {{ $statusStatusClass }}"><span class="s-status-dot"></span> {{ $status->label() }}</span>

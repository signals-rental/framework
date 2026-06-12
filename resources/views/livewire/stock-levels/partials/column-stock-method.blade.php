@php
    $serialised = $item->isSerialised();
@endphp
<span class="s-badge {{ $serialised ? 's-badge-blue' : 's-badge-zinc' }}">{{ $serialised ? 'Serialised' : 'Bulk' }}</span>

@php $statusEnum = $row->status_id; @endphp
<span class="s-badge {{ $statusEnum === \App\Enums\ActivityStatus::Completed ? 's-badge-green' : ($statusEnum === \App\Enums\ActivityStatus::Cancelled ? 's-badge-zinc' : 's-badge-amber') }}">
    {{ $statusEnum->label() }}
</span>

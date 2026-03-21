@php $priorityEnum = $item->priority; @endphp
<span class="s-badge {{ $priorityEnum === \App\Enums\ActivityPriority::High ? 's-badge-red' : ($priorityEnum === \App\Enums\ActivityPriority::Low ? 's-badge-zinc' : 's-badge-blue') }}">
    {{ $priorityEnum->label() }}
</span>

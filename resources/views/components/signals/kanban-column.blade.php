@props([
    'title' => '',
    'count' => null,
])

<div {{ $attributes->merge(['class' => 's-kanban-column']) }}>
    <div class="s-kanban-column-header">
        <span>{{ $title }}</span>
        @if($count !== null)
            <span class="s-kanban-column-count">{{ $count }}</span>
        @endif
    </div>
    <div class="s-kanban-cards">
        {{ $slot }}
    </div>
</div>

@props([])

<div {{ $attributes->merge(['class' => 's-kanban-card']) }} draggable="true">
    {{ $slot }}
</div>

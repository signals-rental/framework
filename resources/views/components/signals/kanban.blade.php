@props([])

<div {{ $attributes->merge(['class' => 's-kanban']) }}>
    {{ $slot }}
</div>

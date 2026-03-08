@props([])

<div {{ $attributes->merge(['class' => 's-timeline']) }}>
    {{ $slot }}
</div>

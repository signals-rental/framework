@props([])

<div {{ $attributes->merge(['class' => 's-avatar-group']) }}>
    {{ $slot }}
</div>

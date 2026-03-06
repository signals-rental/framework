@props(['label' => null])

<div {{ $attributes->merge(['class' => 's-legend']) }}>
    @if($label)<span class="s-legend-label">{{ $label }}</span>@endif
    {{ $slot }}
</div>

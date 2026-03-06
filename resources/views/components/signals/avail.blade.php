@props(['label' => null, 'percent' => 0, 'color' => 'green'])

<div {{ $attributes->merge(['class' => 's-avail']) }}>
    <div class="s-avail-track"><div class="s-avail-fill s-avail-fill-{{ $color }}" style="width: {{ $percent }}%;"></div></div>
    @if($label)<span class="s-avail-label">{{ $label }}</span>@endif
    {{ $slot }}
</div>

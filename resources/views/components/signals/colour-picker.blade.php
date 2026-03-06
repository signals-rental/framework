@props(['color' => '#059669'])

<div {{ $attributes->merge(['class' => 's-colour-picker']) }}>
    <div class="s-colour-swatch" style="background: {{ $color }};"></div>
    <span class="s-colour-value">{{ $color }}</span>
</div>

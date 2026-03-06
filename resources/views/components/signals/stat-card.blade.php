@props(['label' => null, 'value' => null, 'color' => 'blue'])

<div {{ $attributes->merge(['class' => 's-stat-card']) }}>
    <div class="s-stat-icon s-stat-icon-{{ $color }}">
        @isset($icon){{ $icon }}@endisset
    </div>
    <div>
        @if($label)<div class="s-stat-label">{{ $label }}</div>@endif
        @if($value)<div class="s-stat-value">{{ $value }}</div>@endif
    </div>
</div>

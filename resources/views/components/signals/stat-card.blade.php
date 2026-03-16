@props(['label' => null, 'value' => null, 'color' => 'blue', 'trend' => null, 'trendUp' => true])

<div {{ $attributes->merge(['class' => 's-stat-card']) }}>
    <div class="s-stat-icon s-stat-icon-{{ $color }}">
        @isset($icon){{ $icon }}@endisset
    </div>
    <div style="flex: 1; min-width: 0;">
        @if($label)<div class="s-stat-label">{{ $label }}</div>@endif
        @if($value)
            <div class="s-stat-value">
                {{ $value }}
                @if($trend)<span class="s-stat-trend {{ $trendUp ? 's-stat-trend-up' : 's-stat-trend-down' }}">{{ $trend }}</span>@endif
            </div>
        @endif
    </div>
    @isset($sparkline)
        <div style="align-self: flex-end;">{{ $sparkline }}</div>
    @endisset
</div>

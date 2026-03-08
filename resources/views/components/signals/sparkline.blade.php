@props([
    'data' => [],
    'color' => 'green',
    'size' => 'md',
])

@php
    $trendClass = match($color) {
        'red' => 's-sparkline-down',
        'blue', 'neutral' => 's-sparkline-neutral',
        default => 's-sparkline-up',
    };
    $sizeClass = "s-sparkline-{$size}";
    $width = $size === 'sm' ? 48 : 80;
    $height = $size === 'sm' ? 16 : 24;

    $points = '';
    if (count($data) > 1) {
        $min = min($data);
        $max = max($data);
        $range = $max - $min ?: 1;
        $stepX = $width / (count($data) - 1);
        $coords = [];
        foreach ($data as $i => $val) {
            $x = round($i * $stepX, 1);
            $y = round($height - (($val - $min) / $range) * ($height - 2) - 1, 1);
            $coords[] = "{$x},{$y}";
        }
        $points = implode(' ', $coords);
    }
@endphp

<span {{ $attributes->merge(['class' => "s-sparkline {$sizeClass} {$trendClass}"]) }}>
    @if($points)
        <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none">
            <polyline points="{{ $points }}" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @endif
</span>

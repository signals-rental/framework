@props([
    'type' => 'text',
    'lines' => 3,
    'width' => null,
])

@php
    $style = $width ? "width: {$width};" : '';
@endphp

@if($type === 'text')
    <div {{ $attributes }}>
        @for($i = 0; $i < $lines; $i++)
            @if($i === $lines - 1)
                <div class="s-skeleton s-skeleton-text-sm" style="{{ $style }}"></div>
            @else
                <div class="s-skeleton s-skeleton-text" style="{{ $style }}"></div>
            @endif
        @endfor
    </div>
@elseif($type === 'circle')
    <div {{ $attributes->merge(['class' => 's-skeleton s-skeleton-circle']) }} style="{{ $style }}"></div>
@elseif($type === 'rect')
    <div {{ $attributes->merge(['class' => 's-skeleton s-skeleton-rect']) }} style="{{ $style }}"></div>
@elseif($type === 'btn')
    <div {{ $attributes->merge(['class' => 's-skeleton s-skeleton-btn']) }} style="{{ $style }}"></div>
@endif

@props([
    'src' => null,
    'initials' => null,
    'size' => 'md',
    'indicator' => null,
    'color' => null,
])

@php
    $sizeClass = 's-avatar-' . $size;
    $colorClass = $color ? 's-avatar-' . $color : '';
@endphp

<div {{ $attributes->merge(['class' => "s-avatar {$sizeClass} {$colorClass}"]) }}>
    @if($src)
        <img class="s-avatar-img" src="{{ $src }}" alt="">
    @elseif($initials)
        <span class="s-avatar-initials">{{ $initials }}</span>
    @endif
    @if($indicator)
        <span class="s-avatar-indicator s-avatar-indicator-{{ $indicator }}"></span>
    @endif
</div>

@props(['title' => null])

<div {{ $attributes->merge(['class' => 's-tooltip']) }}>
    @if($title)
        <div class="s-tooltip-title">{{ $title }}</div>
    @endif
    {{ $slot }}
</div>

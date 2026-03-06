@props(['label' => null, 'open' => false])

<div {{ $attributes->merge(['class' => 's-collapsible']) }} x-data="{ open: @js($open) }">
    <div class="s-collapsible-toggle" x-on:click="open = !open">
        <span class="s-collapsible-label">{{ $label }}</span>
        <svg class="s-collapsible-chevron" x-bind:class="{ 'open': open }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="s-collapsible-body" x-show="open" x-cloak>
        {{ $slot }}
    </div>
</div>

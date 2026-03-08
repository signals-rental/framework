@props([
    'id' => null,
    'label' => '',
])

@php
    $itemId = $id ?? Str::slug($label) . '-' . Str::random(4);
@endphp

<div {{ $attributes->merge(['class' => 's-accordion-item']) }}>
    <button class="s-accordion-trigger" type="button" x-on:click="toggle(@js($itemId))">
        <span>{{ $label }}</span>
        <svg class="s-accordion-trigger-icon" x-bind:class="{ 'open': isOpen(@js($itemId)) }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <div class="s-accordion-panel" x-show="isOpen(@js($itemId))" x-cloak x-collapse>
        {{ $slot }}
    </div>
</div>

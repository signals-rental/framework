@props([
    'label' => 'Action',
    'variant' => null,
])

@php
    $variantClass = match($variant) {
        'primary' => 's-btn-primary',
        'danger' => 's-btn-danger',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => 's-btn-split']) }} x-data="{ open: false }">
    <button class="s-btn {{ $variantClass }} s-btn-split-main" type="button">
        {{ $label }}
    </button>
    <button class="s-btn {{ $variantClass }} s-btn-split-trigger" type="button" x-on:click="open = !open">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <div class="s-btn-split-menu" x-show="open" x-on:click.outside="open = false" x-cloak>
        <x-signals.dropdown>
            {{ $slot }}
        </x-signals.dropdown>
    </div>
</div>

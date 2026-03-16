@props([
    'label' => 'Action',
    'variant' => null,
    'size' => null,
])

@php
    $variantClass = match($variant) {
        'primary' => 's-btn-primary',
        'danger' => 's-btn-danger',
        default => '',
    };
    $sizeClass = $size ? "s-btn-{$size}" : '';
@endphp

<div {{ $attributes->merge(['class' => 's-btn-split']) }} x-data="{ open: false }" x-ref="splitWrap">
    <button class="s-btn {{ $variantClass }} {{ $sizeClass }} s-btn-split-main" type="button" x-on:click="open = !open">
        {{ $label }}
    </button>
    <button class="s-btn {{ $variantClass }} {{ $sizeClass }} s-btn-split-trigger" type="button" x-on:click="open = !open">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <div x-show="open" x-on:click.outside="open = false" x-cloak
        style="position: fixed; z-index: 9999;"
        x-ref="splitMenu"
        x-init="$watch('open', value => {
            if (value) {
                $nextTick(() => {
                    const wrap = $refs.splitWrap;
                    const rect = wrap.getBoundingClientRect();
                    $refs.splitMenu.style.top = rect.bottom + 4 + 'px';
                    $refs.splitMenu.style.right = (window.innerWidth - rect.right) + 'px';
                    $refs.splitMenu.style.left = 'auto';
                });
            }
        })"
    >
        <x-signals.dropdown style="position: static; left: auto;">
            {{ $slot }}
        </x-signals.dropdown>
    </div>
</div>

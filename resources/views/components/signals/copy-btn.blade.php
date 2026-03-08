@props([
    'text' => '',
    'label' => null,
])

<button
    {{ $attributes->merge(['class' => 's-copy-btn', 'type' => 'button']) }}
    x-data="{ copied: false }"
    x-on:click="navigator.clipboard.writeText(@js($text)); copied = true; setTimeout(() => copied = false, 2000)"
    x-bind:class="{ 'copied': copied }"
>
    <template x-if="!copied">
        <svg class="s-copy-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
    </template>
    <template x-if="copied">
        <svg class="s-copy-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    </template>
    <span x-text="copied ? 'Copied' : @js($label ?? 'Copy')"></span>
</button>

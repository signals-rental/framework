@props([
    'name' => null,
    'title' => null,
    'size' => 'md',
])

@php
    $sizeClass = 's-modal-' . $size;
@endphp

<div
    {{ $attributes->merge(['class' => '']) }}
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === @js($name) || $event.detail?.id === $el.id) open = true"
    x-on:close-modal.window="if ($event.detail === @js($name) || $event.detail?.id === $el.id) open = false"
>
    <template x-teleport="body">
        <div
            class="s-modal-backdrop"
            x-show="open"
            x-cloak
            x-transition.opacity
            x-on:click.self="open = false"
            x-on:keydown.escape.window="open = false"
        >
            <div class="{{ $sizeClass }} s-modal" x-trap.noscroll="open">
                @if($title)
                    <div class="s-modal-header">
                        <span class="s-modal-title">{{ $title }}</span>
                        <button class="s-modal-close" type="button" x-on:click="open = false">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                @endif
                <div class="s-modal-body">
                    {{ $slot }}
                </div>
                @isset($footer)
                    <div class="s-modal-footer">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        </div>
    </template>
</div>

@props([
    'name' => null,
    'title' => null,
    'side' => 'right',
    'size' => 'md',
])

@php
    $sideClass = "s-drawer-{$side}";
    $sizeClass = "s-drawer-{$size}";
    $translateFrom = $side === 'left' ? '-translate-x-full' : 'translate-x-full';
@endphp

<div
    {{ $attributes }}
    x-data="{ open: false }"
    x-on:open-drawer.window="if ($event.detail === @js($name) || $event.detail?.id === $el.id) open = true"
    x-on:close-drawer.window="if ($event.detail === @js($name) || $event.detail?.id === $el.id) open = false"
>
    <template x-teleport="body">
        {{-- Backdrop --}}
        <div
            class="s-drawer-backdrop"
            x-show="open"
            x-cloak
            x-transition.opacity
            x-on:click="open = false"
        ></div>
        {{-- Panel --}}
        <div
            class="{{ $sizeClass }} {{ $sideClass }} s-drawer"
            x-show="open"
            x-cloak
            x-trap.noscroll="open"
            x-on:keydown.escape.window="open = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="{{ $translateFrom }}"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="{{ $translateFrom }}"
        >
            @if($title)
                <div class="s-drawer-header">
                    <span class="s-drawer-title">{{ $title }}</span>
                    <button class="s-drawer-close" type="button" x-on:click="open = false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            @endif
            <div class="s-drawer-body">
                {{ $slot }}
            </div>
            @isset($footer)
                <div class="s-drawer-footer">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </template>
</div>

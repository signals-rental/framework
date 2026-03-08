@props([
    'position' => 'bottom',
    'title' => null,
])

<div {{ $attributes->merge(['class' => 's-popover-wrap']) }} x-data="{ open: false }">
    <div x-on:click="open = !open">
        {{ $trigger }}
    </div>
    <div
        class="s-popover s-popover-{{ $position }}"
        x-show="open"
        x-on:click.outside="open = false"
        x-on:keydown.escape.window="open = false"
        x-cloak
    >
        @if($title)
            <div class="s-popover-title">{{ $title }}</div>
        @endif
        <div class="s-popover-body">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="s-popover-footer">{{ $footer }}</div>
        @endisset
    </div>
</div>

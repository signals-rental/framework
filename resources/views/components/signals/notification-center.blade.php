@props([
    'count' => 0,
])

<div
    {{ $attributes->merge(['class' => 's-notif-center']) }}
    x-data="{ open: false }"
    x-on:click.outside="open = false"
>
    <button class="s-notif-center-trigger" type="button" x-on:click="open = !open">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        @if($count > 0)
            <span class="s-notif-center-badge">{{ $count > 99 ? '99+' : $count }}</span>
        @endif
    </button>
    <div class="s-notif-center-dropdown" x-show="open" x-cloak x-transition>
        <div class="s-notif-center-header">
            <span>Notifications</span>
            @isset($headerActions)
                {{ $headerActions }}
            @endisset
        </div>
        <div class="s-notif-center-list">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="s-notif-center-footer">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>

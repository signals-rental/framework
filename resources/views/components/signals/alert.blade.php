@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => false,
])

<div
    {{ $attributes->merge(['class' => "s-alert s-alert-{$type}"]) }}
    @if($dismissible) x-data="{ show: true }" x-show="show" x-cloak @endif
>
    @isset($icon)
        <span class="s-alert-icon">{{ $icon }}</span>
    @else
        <span class="s-alert-icon">
            @switch($type)
                @case('success')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    @break
                @case('warning')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    @break
                @case('danger')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    @break
                @default
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            @endswitch
        </span>
    @endisset
    <div class="s-alert-content">
        @if($title)
            <div class="s-alert-title">{{ $title }}</div>
        @endif
        {{ $slot }}
        @isset($actions)
            <div class="s-alert-actions">{{ $actions }}</div>
        @endisset
    </div>
    @if($dismissible)
        <button class="s-alert-dismiss" type="button" x-on:click="show = false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    @endif
</div>

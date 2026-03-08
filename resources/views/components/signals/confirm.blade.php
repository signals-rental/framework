@props([
    'name' => null,
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'type' => 'danger',
])

@php
    $iconClass = "s-confirm-icon-{$type}";
    $btnClass = match($type) {
        'danger' => 's-btn-danger',
        'warning' => 's-btn-warning',
        default => 's-btn-primary',
    };
@endphp

<div
    {{ $attributes }}
    x-data="{ open: false }"
    x-on:open-confirm.window="if ($event.detail === @js($name)) open = true"
    x-on:close-confirm.window="if ($event.detail === @js($name)) open = false"
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
            <div class="s-modal-sm s-modal" x-trap.noscroll="open">
                <div class="s-confirm">
                    <div class="s-confirm-icon {{ $iconClass }}">
                        @switch($type)
                            @case('warning')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                @break
                            @case('info')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                @break
                            @default
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        @endswitch
                    </div>
                    <div class="s-confirm-title">{{ $title }}</div>
                    <div class="s-confirm-message">{{ $message }}</div>
                    <div class="s-confirm-actions">
                        <button class="s-btn s-btn-sm" type="button" x-on:click="open = false">{{ $cancelLabel }}</button>
                        <button class="s-btn s-btn-sm {{ $btnClass }}" type="button" x-on:click="$dispatch('confirmed', @js($name)); open = false">{{ $confirmLabel }}</button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

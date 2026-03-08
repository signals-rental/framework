@props([
    'value' => '',
    'name' => '',
    'type' => 'text',
])

<div
    {{ $attributes->merge(['class' => 's-inline-edit']) }}
    x-data="{ editing: false, val: @js($value), original: @js($value), save() { this.editing = false; $dispatch('inline-saved', { name: @js($name), value: this.val }); this.original = this.val; }, cancel() { this.val = this.original; this.editing = false; } }"
>
    <template x-if="!editing">
        <span class="s-inline-edit-display" x-on:click="editing = true; $nextTick(() => $refs.input.focus())">
            @isset($display)
                {{ $display }}
            @else
                <span x-text="val || '—'"></span>
            @endisset
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
        </span>
    </template>
    <template x-if="editing">
        <span style="display: inline-flex; align-items: center; gap: 4px;">
            <input
                x-ref="input"
                type="{{ $type }}"
                class="s-inline-edit-input"
                x-model="val"
                x-on:keydown.enter="save()"
                x-on:keydown.escape="cancel()"
            >
            <span class="s-inline-edit-actions">
                <button class="s-inline-edit-save" type="button" x-on:click="save()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
                <button class="s-inline-edit-cancel" type="button" x-on:click="cancel()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </span>
        </span>
    </template>
</div>

@props([
    'name' => '',
    'value' => null,
    'placeholder' => 'Search...',
    'options' => [],
    'searchable' => true,
    'clearable' => true,
])

<div
    {{ $attributes->merge(['class' => 's-combobox']) }}
    x-data="{
        open: false,
        search: '',
        value: @js($value),
        options: @js($options),
        activeIndex: -1,
        get filtered() {
            if (!this.search) return this.options;
            const q = this.search.toLowerCase();
            return this.options.filter(o => o.label.toLowerCase().includes(q));
        },
        get displayText() {
            const opt = this.options.find(o => o.value == this.value);
            return opt ? opt.label : '';
        },
        get grouped() {
            const groups = {};
            this.filtered.forEach(o => {
                const g = o.group || '';
                if (!groups[g]) groups[g] = [];
                groups[g].push(o);
            });
            return groups;
        },
        get hasGroups() { return this.filtered.some(o => o.group); },
        select(opt) {
            this.value = opt.value;
            this.search = opt.label;
            this.open = false;
            this.activeIndex = -1;
            $dispatch('combobox-selected', { name: @js($name), value: opt.value, label: opt.label });
        },
        clear() { this.value = null; this.search = ''; $dispatch('combobox-selected', { name: @js($name), value: null, label: '' }); },
        onFocus() { this.open = true; if (!@js($searchable)) return; this.search = ''; },
        onKeydown(e) {
            const items = this.filtered;
            if (e.key === 'ArrowDown') { e.preventDefault(); this.activeIndex = Math.min(this.activeIndex + 1, items.length - 1); this.open = true; }
            else if (e.key === 'ArrowUp') { e.preventDefault(); this.activeIndex = Math.max(this.activeIndex - 1, 0); }
            else if (e.key === 'Enter' && this.activeIndex >= 0 && items[this.activeIndex]) { e.preventDefault(); this.select(items[this.activeIndex]); }
            else if (e.key === 'Escape') { this.open = false; this.search = this.displayText; }
        },
        init() { this.search = this.displayText; }
    }"
    x-on:click.outside="open = false; search = displayText"
>
    <input type="hidden" name="{{ $name }}" x-bind:value="value">
    <input
        type="text"
        class="s-combobox-input"
        x-model="search"
        x-on:focus="onFocus()"
        x-on:input="open = true; activeIndex = -1"
        x-on:keydown="onKeydown($event)"
        placeholder="{{ $placeholder }}"
        @if(!$searchable) readonly @endif
    >
    @if($clearable)
        <button class="s-combobox-clear" type="button" x-show="value" x-on:click="clear()" x-cloak>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    @endif
    <div class="s-combobox-dropdown" x-show="open && filtered.length > 0" x-cloak>
        <template x-if="hasGroups">
            <template x-for="(group, label) in grouped" :key="label">
                <div class="s-combobox-group">
                    <template x-if="label">
                        <div class="s-combobox-group-label" x-text="label"></div>
                    </template>
                    <template x-for="(opt, i) in group" :key="opt.value">
                        <button
                            class="s-combobox-option"
                            type="button"
                            x-text="opt.label"
                            x-on:click="select(opt)"
                            x-bind:class="{ 'selected': opt.value == value }"
                        ></button>
                    </template>
                </div>
            </template>
        </template>
        <template x-if="!hasGroups">
            <template x-for="(opt, i) in filtered" :key="opt.value">
                <button
                    class="s-combobox-option"
                    type="button"
                    x-text="opt.label"
                    x-on:click="select(opt)"
                    x-bind:class="{ 'selected': opt.value == value, 'active': i === activeIndex }"
                ></button>
            </template>
        </template>
    </div>
    <div class="s-combobox-empty" x-show="open && filtered.length === 0 && search" x-cloak>No results found</div>
</div>

@props([
    'name' => '',
    'value' => [],
    'placeholder' => 'Add tag...',
    'suggestions' => [],
])

<div
    {{ $attributes->merge(['class' => 's-tag-input']) }}
    style="position: relative;"
    x-data="{
        tags: @js($value),
        input: '',
        suggestions: @js($suggestions),
        filtered: [],
        showSuggestions: false,
        activeIndex: -1,
        addTag(tag) {
            tag = tag.trim();
            if (tag && !this.tags.includes(tag)) {
                this.tags.push(tag);
                $dispatch('tags-changed', this.tags);
            }
            this.input = '';
            this.showSuggestions = false;
            this.activeIndex = -1;
        },
        removeTag(index) {
            this.tags.splice(index, 1);
            $dispatch('tags-changed', this.tags);
        },
        filter() {
            if (!this.input) { this.filtered = []; this.showSuggestions = false; return; }
            this.filtered = this.suggestions.filter(s => s.toLowerCase().includes(this.input.toLowerCase()) && !this.tags.includes(s));
            this.showSuggestions = this.filtered.length > 0;
            this.activeIndex = -1;
        },
        onKeydown(e) {
            if (e.key === 'Enter') { e.preventDefault(); if (this.activeIndex >= 0 && this.filtered[this.activeIndex]) { this.addTag(this.filtered[this.activeIndex]); } else if (this.input) { this.addTag(this.input); } }
            else if (e.key === 'Backspace' && !this.input && this.tags.length) { this.removeTag(this.tags.length - 1); }
            else if (e.key === 'ArrowDown' && this.showSuggestions) { e.preventDefault(); this.activeIndex = Math.min(this.activeIndex + 1, this.filtered.length - 1); }
            else if (e.key === 'ArrowUp' && this.showSuggestions) { e.preventDefault(); this.activeIndex = Math.max(this.activeIndex - 1, 0); }
            else if (e.key === 'Escape') { this.showSuggestions = false; }
        }
    }"
    x-on:click="$refs.field.focus()"
>
    @if($name)
        <template x-for="(tag, i) in tags" :key="i">
            <input type="hidden" :name="@js($name) + '[]'" :value="tag">
        </template>
    @endif

    <template x-for="(tag, i) in tags" :key="'tag-' + i">
        <span class="s-tag-input-tag">
            <span x-text="tag"></span>
            <button class="s-tag-input-tag-remove" type="button" x-on:click.stop="removeTag(i)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </span>
    </template>

    <input
        x-ref="field"
        class="s-tag-input-field"
        type="text"
        x-model="input"
        x-on:input="filter()"
        x-on:keydown="onKeydown($event)"
        x-on:blur="setTimeout(() => showSuggestions = false, 150)"
        placeholder="{{ $placeholder }}"
    >

    <div class="s-tag-input-suggestions" x-show="showSuggestions" x-cloak>
        <template x-for="(s, i) in filtered" :key="s">
            <button type="button" x-text="s" x-on:mousedown.prevent="addTag(s)" x-bind:class="{ 'active': i === activeIndex }"></button>
        </template>
    </div>
</div>

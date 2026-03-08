@props([
    'value' => null,
    'placeholder' => 'Select date',
    'format' => 'Y-m-d',
    'range' => false,
    'rangeStart' => null,
    'rangeEnd' => null,
    'showTime' => false,
])

<div
    {{ $attributes->merge(['class' => 's-datepicker']) }}
    x-data="{
        open: false,
        value: @js($value),
        rangeMode: @js($range),
        rangeStart: @js($rangeStart),
        rangeEnd: @js($rangeEnd),
        showTime: @js($showTime),
        time: @js($showTime && $value ? \Carbon\Carbon::parse($value)->format('H:i') : '12:00'),
        get display() {
            if (this.rangeMode) {
                if (!this.rangeStart) return '';
                const fmt = (d) => {
                    const dt = new Date(d + 'T00:00:00');
                    return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                };
                if (this.rangeEnd) return fmt(this.rangeStart) + ' → ' + fmt(this.rangeEnd);
                return fmt(this.rangeStart) + ' → ...';
            }
            if (!this.value) return '';
            const d = new Date(this.value + 'T00:00:00');
            let str = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            if (this.showTime) str += ' ' + this.time;
            return str;
        },
        selectDate(e) {
            this.value = e.detail.date;
            if (!this.showTime) this.open = false;
            this.$dispatch('input', this.showTime ? this.value + ' ' + this.time : this.value);
        },
        selectRange(e) {
            this.rangeStart = e.detail.start;
            this.rangeEnd = e.detail.end;
            this.open = false;
            this.$dispatch('range-input', { start: this.rangeStart, end: this.rangeEnd });
        },
        updateTime(val) {
            this.time = val;
            if (this.value) {
                this.$dispatch('input', this.value + ' ' + this.time);
            }
        },
        clear() {
            this.value = null;
            this.rangeStart = null;
            this.rangeEnd = null;
            this.$dispatch('input', null);
        },
    }"
    x-on:date-selected.stop="selectDate"
    x-on:range-selected.stop="selectRange"
>
    <div class="s-datepicker-input" x-on:click="open = !open">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <input type="text" readonly x-bind:value="display" placeholder="{{ $placeholder }}">
        <template x-if="value || rangeStart">
            <button class="s-datepicker-clear" type="button" x-on:click.stop="clear">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </template>
    </div>
    <div class="s-datepicker-dropdown" x-show="open" x-on:click.outside="open = false" x-cloak>
        @if($range)
            <x-signals.calendar :range-start="$rangeStart" :range-end="$rangeEnd" />
        @else
            <x-signals.calendar :selected="$value" />
        @endif
        @if($showTime)
            <div style="padding: 8px 12px; border-top: 1px solid var(--card-border); display: flex; align-items: center; gap: 8px; background: var(--card-bg);">
                <svg style="width: 14px; height: 14px; color: var(--text-muted);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <input type="time" class="s-datetime-input-time" x-bind:value="time" x-on:input="updateTime($event.target.value)" style="flex: 1;">
            </div>
        @endif
    </div>
</div>

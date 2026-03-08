@props([
    'selected' => null,
    'rangeStart' => null,
    'rangeEnd' => null,
    'events' => [],
    'showAdjacentMonths' => false,
])

<div
    {{ $attributes->merge(['class' => 's-calendar']) }}
    x-data="{
        month: {{ $selected ? "new Date('" . $selected . "').getMonth()" : ($rangeStart ? "new Date('" . $rangeStart . "').getMonth()" : 'new Date().getMonth()') }},
        year: {{ $selected ? "new Date('" . $selected . "').getFullYear()" : ($rangeStart ? "new Date('" . $rangeStart . "').getFullYear()" : 'new Date().getFullYear()') }},
        selected: {{ $selected ? "'" . $selected . "'" : 'null' }},
        rangeStart: {{ $rangeStart ? "'" . $rangeStart . "'" : 'null' }},
        rangeEnd: {{ $rangeEnd ? "'" . $rangeEnd . "'" : 'null' }},
        rangeMode: {{ ($rangeStart !== null || $rangeEnd !== null) ? 'true' : 'false' }},
        events: @js($events),
        showAdjacentMonths: @js($showAdjacentMonths),
        get title() {
            return new Date(this.year, this.month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        },
        get prevTitle() {
            const m = this.month === 0 ? 11 : this.month - 1;
            const y = this.month === 0 ? this.year - 1 : this.year;
            return new Date(y, m).toLocaleDateString('en-US', { month: 'short' });
        },
        get nextTitle() {
            const m = this.month === 11 ? 0 : this.month + 1;
            const y = this.month === 11 ? this.year + 1 : this.year;
            return new Date(y, m).toLocaleDateString('en-US', { month: 'short' });
        },
        get days() {
            const first = new Date(this.year, this.month, 1);
            const last = new Date(this.year, this.month + 1, 0);
            const startDay = first.getDay();
            const result = [];
            const prevLast = new Date(this.year, this.month, 0).getDate();
            const prevMonth = this.month === 0 ? 11 : this.month - 1;
            const prevYear = this.month === 0 ? this.year - 1 : this.year;
            for (let i = startDay - 1; i >= 0; i--) {
                const d = prevLast - i;
                const dateStr = `${prevYear}-${String(prevMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                result.push({ day: d, current: false, dateStr });
            }
            const today = new Date();
            for (let d = 1; d <= last.getDate(); d++) {
                const dateStr = `${this.year}-${String(this.month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                result.push({
                    day: d,
                    current: true,
                    dateStr,
                    isToday: today.getFullYear() === this.year && today.getMonth() === this.month && today.getDate() === d,
                    isSelected: this.selected === dateStr,
                    hasEvent: this.events.includes(dateStr),
                });
            }
            const nextMonth = this.month === 11 ? 0 : this.month + 1;
            const nextYear = this.month === 11 ? this.year + 1 : this.year;
            const remaining = 42 - result.length;
            for (let d = 1; d <= remaining; d++) {
                const dateStr = `${nextYear}-${String(nextMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                result.push({ day: d, current: false, dateStr });
            }
            return result;
        },
        isRangeStart(dateStr) { return this.rangeStart === dateStr; },
        isRangeEnd(dateStr) { return this.rangeEnd === dateStr; },
        isInRange(dateStr) {
            if (!this.rangeStart || !this.rangeEnd) return false;
            return dateStr > this.rangeStart && dateStr < this.rangeEnd;
        },
        prev() { if (this.month === 0) { this.month = 11; this.year--; } else { this.month--; } },
        next() { if (this.month === 11) { this.month = 0; this.year++; } else { this.month++; } },
        select(day) {
            if (!day.current && !this.showAdjacentMonths) return;
            if (this.rangeMode) {
                if (!this.rangeStart || (this.rangeStart && this.rangeEnd)) {
                    this.rangeStart = day.dateStr;
                    this.rangeEnd = null;
                } else {
                    if (day.dateStr < this.rangeStart) {
                        this.rangeEnd = this.rangeStart;
                        this.rangeStart = day.dateStr;
                    } else {
                        this.rangeEnd = day.dateStr;
                    }
                    this.$dispatch('range-selected', { start: this.rangeStart, end: this.rangeEnd });
                }
            } else {
                this.selected = day.dateStr;
                this.$dispatch('date-selected', { date: day.dateStr });
            }
        },
    }"
>
    <div class="s-calendar-header">
        @if($showAdjacentMonths)
            <button class="s-calendar-nav-btn" type="button" x-on:click="prev" x-text="'← ' + prevTitle" style="font-family: var(--font-display); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; width: auto; padding: 0 8px;"></button>
        @else
            <button class="s-calendar-nav-btn" type="button" x-on:click="prev">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
        @endif
        <span class="s-calendar-title" x-text="title"></span>
        @if($showAdjacentMonths)
            <button class="s-calendar-nav-btn" type="button" x-on:click="next" x-text="nextTitle + ' →'" style="font-family: var(--font-display); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; width: auto; padding: 0 8px;"></button>
        @else
            <button class="s-calendar-nav-btn" type="button" x-on:click="next">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        @endif
    </div>
    <div class="s-calendar-grid">
        <template x-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']">
            <div class="s-calendar-dow" x-text="d"></div>
        </template>
        <template x-for="(day, idx) in days" :key="idx">
            <div
                class="s-calendar-day"
                x-bind:class="{
                    'today': day.isToday,
                    'selected': day.isSelected,
                    'other-month': !day.current,
                    'range-start': isRangeStart(day.dateStr),
                    'range-end': isRangeEnd(day.dateStr),
                    'in-range': isInRange(day.dateStr),
                }"
                x-on:click="select(day)"
                x-text="day.day"
            >
            </div>
        </template>
    </div>
</div>

@php
    use Carbon\Carbon;

    /** @var \Illuminate\Support\Collection<int, \App\Data\Calendar\CalendarEventData> $events */
    $events = $this->events;
    $weekendShaded = $this->weekendShaded();
    $days = $this->visibleDays();
    $currentMonth = Carbon::parse($startDate)->month;
    $timeFormat = (string) (settings('company.time_format_php') ?? 'H:i');

    // Day-of-week header labels, ordered from the configured week start.
    $firstDay = (int) settings('preferences.first_day_of_week');
    $dowLabels = [];
    for ($i = 0; $i < 7; $i++) {
        $dowLabels[] = Carbon::now()->startOfWeek($firstDay)->addDays($i)->format('D');
    }

    // An event shows on every day it covers (multi-day events span consecutive cells).
    $coversDay = function ($e, $day): bool {
        if ($e->starts_at === null) {
            return false;
        }
        // Compare local (display-tz) calendar dates as strings: timestamps are
        // stored UTC and rendered in the company timezone, so instant comparison
        // against an app-tz $day cursor misclassifies events at the day boundary.
        $dayStr = $day->toDateString();
        $startStr = Carbon::parse($e->starts_at)->toDateString();
        $endStr = $e->ends_at !== null ? Carbon::parse($e->ends_at)->toDateString() : $startStr;

        return $startStr <= $dayStr && $dayStr <= $endStr;
    };
@endphp

<div class="s-cal flex-1 min-h-0 overflow-auto">
    <div class="s-cal-month">
        @foreach($dowLabels as $label)
            <div class="s-cal-month-dow" wire:key="month-dow-{{ $label }}">{{ $label }}</div>
        @endforeach

        @foreach($days as $day)
            @php
                $isToday = $day->isToday();
                $otherMonth = $day->month !== $currentMonth;
                $isWeekend = $day->isWeekend();
                $dayEvents = $events
                    ->filter(fn ($e) => $coversDay($e, $day))
                    ->sortBy(fn ($e) => Carbon::parse($e->starts_at)->format('H:i'))
                    ->values();
                $visibleChips = $dayEvents->take(3);
                $overflow = $dayEvents->count() - $visibleChips->count();
            @endphp
            <div class="s-cal-month-cell {{ $isToday ? 'today' : '' }} {{ $otherMonth ? 'other-month' : '' }} {{ ($weekendShaded && $isWeekend) ? 'nonwork' : '' }}"
                 wire:key="month-cell-{{ $day->toDateString() }}"
                 x-on:click.self="$dispatch('open-modal', 'calendar-activity-form'); $wire.dispatch('calendar-open-form', { date: '{{ $day->toDateString() }}' })">
                <div class="s-cal-month-daynum">{{ $day->format('j') }}</div>

                @foreach($visibleChips as $event)
                    @php $tint = str_replace('s-avatar-', '', $event->owner_color); @endphp
                    <div class="s-cal-chip s-cal-chip-{{ $tint }} {{ $event->status_id === \App\Enums\ActivityStatus::Completed->value ? 'completed' : '' }} {{ $event->status_id === \App\Enums\ActivityStatus::Cancelled->value ? 'cancelled' : '' }}"
                         wire:key="month-chip-{{ $event->id }}"
                         x-on:click.stop="$dispatch('open-modal', 'calendar-activity-detail'); $wire.dispatch('calendar-open-detail', { activityId: {{ $event->id }} })">
                        <span class="s-cal-chip-dot"></span>
                        @unless($event->all_day)
                            <span class="s-cal-chip-time">{{ Carbon::parse($event->starts_at)->format($timeFormat) }}</span>
                        @endunless
                        <span class="s-cal-chip-title">{{ $event->subject }}</span>
                        @if($event->regarding_name)<span class="s-cal-chip-sub">{{ $event->regarding_name }}</span>@endif
                    </div>
                @endforeach

                @if($overflow > 0)
                    <span class="s-cal-more" wire:click="openDayView('{{ $day->toDateString() }}')">+{{ $overflow }} more</span>
                @endif
            </div>
        @endforeach
    </div>
</div>

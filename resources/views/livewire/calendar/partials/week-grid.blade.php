@php
    use Carbon\Carbon;

    /** @var \Illuminate\Support\Collection<int, \App\Data\Calendar\CalendarEventData> $events */
    $events = $this->events;
    [$winStart, $winEnd] = $this->hourWindow();
    $totalMin = max(60, ($winEnd - $winStart) * 60);
    $workStart = (int) Carbon::parse((string) settings('scheduling.default_start_time'))->format('H');
    $workEnd = (int) Carbon::parse((string) settings('scheduling.default_end_time'))->format('H');
    $workStartStr = Carbon::parse((string) settings('scheduling.default_start_time'))->format('H:i');
    $workEndStr = Carbon::parse((string) settings('scheduling.default_end_time'))->format('H:i');
    $timeFormat = (string) (settings('company.time_format_php') ?? 'H:i');
    // user_id => photo URL map, resolved once per render (not per event) so event-pane avatars can show real photos.
    $staffPhotos = $this->staff->mapWithKeys(fn ($u) => [(int) $u->id => app(\App\Services\FileService::class)->signedUrlOrNull($u->member?->icon_thumb_url)])->all();
    $weekendShaded = $this->weekendShaded();
    $days = $this->visibleDays();
    $dayCount = count($days);
    $weekStart = $days[0];
    $weekEnd = $days[$dayCount - 1];

    $lanePct = fn (int $lane, int $lanes): float => $lanes > 0 ? ($lane / $lanes) * 100 : 0;
    $widthPct = fn (int $lanes): float => $lanes > 0 ? (100 / $lanes) : 100;

    // "All-day" = an event that exactly spans the configured working day.
    $isBandEvent = function ($e) use ($workStartStr, $workEndStr): bool {
        return $e->starts_at !== null && $e->ends_at !== null
            && Carbon::parse($e->starts_at)->format('H:i') === $workStartStr
            && Carbon::parse($e->ends_at)->format('H:i') === $workEndStr;
    };

    // Band events laid out as bars spanning the days they cover. Column offsets are
    // derived from local calendar dates (Y-m-d strings) so they stay correct when the
    // app tz (UTC) and display tz (Europe/London) differ.
    $weekStartStr = $weekStart->toDateString();
    $weekEndStr = $weekEnd->toDateString();
    $banded = $events
        ->filter(fn ($e) => $isBandEvent($e))
        ->map(function ($e) use ($weekStartStr, $weekEndStr, $dayCount) {
            $startStr = Carbon::parse($e->starts_at)->toDateString();
            $endStr = Carbon::parse($e->ends_at)->toDateString();
            $startCol = $startStr <= $weekStartStr
                ? 0
                : (int) Carbon::parse($weekStartStr)->diffInDays(Carbon::parse($startStr));
            $endCol = $endStr >= $weekEndStr
                ? $dayCount - 1
                : (int) Carbon::parse($weekStartStr)->diffInDays(Carbon::parse($endStr));
            $startCol = max(0, min($dayCount - 1, $startCol));
            $endCol = max($startCol, min($dayCount - 1, $endCol));

            return ['event' => $e, 'start_min' => $startCol, 'end_min' => $endCol + 1, 'start_col' => $startCol, 'end_col' => $endCol];
        })
        ->values()
        ->all();
    $bandedAllocated = app(\App\Services\Calendar\EventLaneAllocator::class)->allocate($banded);
    $bandLanes = collect($bandedAllocated)->max('lanes') ?: 1;
@endphp

<div class="s-cal flex-1 min-h-0">
    {{-- All-day band: events that exactly span the working day, drawn as spanning bars --}}
    @if(count($bandedAllocated) > 0)
        <div class="s-cal-allday">
            <div class="s-cal-allday-label">All day</div>
            <div class="s-cal-allday-grid" style="grid-template-rows: repeat({{ $bandLanes }}, 19px);">
                @foreach($bandedAllocated as $row)
                    @php $event = $row['event']; $tint = str_replace('s-avatar-', '', $event->owner_color); @endphp
                    <div class="s-cal-allday-bar s-cal-event-{{ $tint }} {{ $event->status_id === \App\Enums\ActivityStatus::Completed->value ? 'completed' : '' }} {{ $event->status_id === \App\Enums\ActivityStatus::Cancelled->value ? 'cancelled' : '' }}"
                         style="grid-column: {{ $row['start_col'] + 1 }} / span {{ $row['end_col'] - $row['start_col'] + 1 }}; grid-row: {{ $row['lane'] + 1 }};"
                         wire:key="week-band-{{ $event->id }}"
                         x-on:click="$dispatch('open-modal', 'calendar-activity-detail'); $wire.dispatch('calendar-open-detail', { activityId: {{ $event->id }} })">
                        {{ $event->subject }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="s-cal-grid">
        <div class="s-cal-gutter">
            <div class="s-cal-gutter-spacer"></div>
            @for($h = $winStart; $h < $winEnd; $h++)
                <div class="s-cal-hour">{{ Carbon::today()->setTime($h, 0)->format($timeFormat) }}</div>
            @endfor
        </div>

        @foreach($days as $day)
            @php
                $isToday = $day->isToday();
                $isWeekend = $day->isWeekend();
                $timed = $events
                    ->filter(function ($e) use ($isBandEvent, $day) {
                        if ($isBandEvent($e) || $e->starts_at === null) {
                            return false;
                        }
                        // Compare local calendar dates as strings (timezone-robust).
                        $dayStr = $day->toDateString();
                        $startStr = Carbon::parse($e->starts_at)->toDateString();
                        $endStr = $e->ends_at !== null ? Carbon::parse($e->ends_at)->toDateString() : $startStr;

                        return $startStr <= $dayStr && $dayStr <= $endStr;
                    })
                    ->map(function ($e) use ($winStart, $totalMin, $day) {
                        $start = Carbon::parse($e->starts_at);
                        $end = $e->ends_at !== null ? Carbon::parse($e->ends_at) : $start->copy()->addHour();
                        // Compare local calendar dates as strings (timezone-robust).
                        $dayStr = $day->toDateString();
                        $startsToday = $start->toDateString() === $dayStr;
                        $endsToday = $end->toDateString() === $dayStr;
                        // Clip the event to this day's window: fall off the bottom / resume at the top.
                        $segStart = $startsToday ? (($start->hour - $winStart) * 60 + $start->minute) : 0;
                        $segEnd = $endsToday ? (($end->hour - $winStart) * 60 + $end->minute) : $totalMin;
                        $segStart = max(0, min($totalMin, $segStart));
                        $segEnd = max($segStart + 10, min($totalMin, $segEnd));

                        return [
                            'event' => $e,
                            'start_min' => $segStart,
                            'end_min' => $segEnd,
                            'starts_today' => $startsToday,
                            'continues_before' => $start->toDateString() < $dayStr,
                            'continues_after' => $end->toDateString() > $dayStr,
                        ];
                    })
                    ->values()
                    ->all();
                $allocated = app(\App\Services\Calendar\EventLaneAllocator::class)->allocate($timed);
            @endphp
            <div class="s-cal-col {{ ($weekendShaded && $isWeekend) ? 's-cal-nonwork' : '' }}" wire:key="week-col-{{ $day->toDateString() }}">
                <div class="s-cal-colhead {{ $isToday ? 'today' : '' }}">
                    <div>
                        <div class="s-cal-colhead-dow">{{ $day->format('D') }}</div>
                        <div class="s-cal-colhead-date">{{ $day->format('j') }}</div>
                    </div>
                </div>

                <div class="s-cal-colbody"
                     x-data="{ slot(e) {
                        const rect = $el.getBoundingClientRect();
                        const pct = Math.max(0, Math.min(1, (e.clientY - rect.top) / rect.height));
                        const mins = Math.round(pct * {{ $totalMin }} / 30) * 30;
                        const hour = {{ $winStart }} + Math.floor(mins / 60);
                        const minute = String(mins % 60).padStart(2, '0');
                        const startsAt = '{{ $day->toDateString() }} ' + String(hour).padStart(2, '0') + ':' + minute;
                        $dispatch('open-modal', 'calendar-activity-form');
                        $wire.dispatch('calendar-open-form', { starts_at: startsAt });
                     } }">
                    @for($h = $winStart; $h < $winEnd; $h++)
                        <div class="s-cal-hour {{ ($h < $workStart || $h >= $workEnd) ? 's-cal-nonwork' : '' }}" x-on:click="slot($event)"></div>
                    @endfor

                    @if($isToday)
                        @php $now = app(\App\Support\Timezone::class)->toLocal(now()); $nowMin = max(0, min($totalMin, ($now->hour - $winStart) * 60 + $now->minute)); @endphp
                        <div class="s-cal-now" style="top: {{ ($nowMin / $totalMin) * 100 }}%;"></div>
                    @endif

                    @foreach($allocated as $row)
                        @php
                            $event = $row['event'];
                            $topPct = ($row['start_min'] / $totalMin) * 100;
                            $heightPct = (($row['end_min'] - $row['start_min']) / $totalMin) * 100;
                            $tint = str_replace('s-avatar-', '', $event->owner_color);
                        @endphp
                        <div class="s-cal-event s-cal-event-{{ $tint }} {{ $row['continues_before'] ? 's-cal-event-continue-top' : '' }} {{ $row['continues_after'] ? 's-cal-event-continue-bottom' : '' }} {{ $event->status_id === \App\Enums\ActivityStatus::Completed->value ? 'completed' : '' }} {{ $event->status_id === \App\Enums\ActivityStatus::Cancelled->value ? 'cancelled' : '' }}"
                             style="top: {{ $topPct }}%; height: {{ $heightPct }}%; left: {{ $lanePct($row['lane'], $row['lanes']) }}%; width: {{ $widthPct($row['lanes']) }}%;"
                             wire:key="week-event-{{ $event->id }}-{{ $day->toDateString() }}"
                             x-on:click.stop="$dispatch('open-modal', 'calendar-activity-detail'); $wire.dispatch('calendar-open-detail', { activityId: {{ $event->id }} })">
                            @if($row['starts_today'])
                                <div class="s-cal-event-time">{{ Carbon::parse($event->starts_at)->format($timeFormat) }}</div>
                            @endif
                            <div class="s-cal-event-title">{{ $event->subject }}</div>
                            @if($event->regarding_name)<div class="s-cal-event-sub">{{ $event->regarding_name }}</div>@endif
                            @include('livewire.calendar.partials.event-meta', ['event' => $event])
                            @php
                                // Owner first, then participants (excluding the owner to avoid a duplicate avatar).
                                $extraParticipants = array_values(array_filter(
                                    $event->participants,
                                    fn ($p) => ($p['user_id'] ?? null) !== $event->owner_id,
                                ));
                                $visibleParticipants = array_slice($extraParticipants, 0, 3);
                                $avatarOverflow = (count($extraParticipants) + 1) - 4;
                            @endphp
                            <div class="s-cal-event-avatars">
                                <x-signals.avatar size="xs" :initials="$event->owner_initials" :src="$staffPhotos[$event->owner_id] ?? null" :color="str_replace('s-avatar-', '', $event->owner_color)" />
                                @foreach($visibleParticipants as $p)
                                    @php
                                        $pInitials = \Illuminate\Support\Str::of($p['name'])->explode(' ')->take(2)->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->implode('');
                                        $pColor = ($p['user_id'] ?? null) !== null
                                            ? str_replace('s-avatar-', '', app(\App\Services\Calendar\OwnerColorResolver::class)->for($p['user_id']))
                                            : 'zinc';
                                    @endphp
                                    <x-signals.avatar size="xs" :initials="$pInitials" :src="$staffPhotos[$p['user_id']] ?? null" :color="$pColor" wire:key="week-avatar-{{ $event->id }}-{{ $day->toDateString() }}-{{ $p['member_id'] }}" />
                                @endforeach
                                @if($avatarOverflow > 0)
                                    <span class="s-cal-event-avatars-more">+{{ $avatarOverflow }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

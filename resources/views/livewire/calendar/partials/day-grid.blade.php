@php
    use Carbon\Carbon;

    /** @var \Illuminate\Support\Collection<int, \App\Data\Calendar\CalendarEventData> $events */
    $events = $this->events;
    [$winStart, $winEnd] = $this->hourWindow();
    $totalMin = max(60, ($winEnd - $winStart) * 60);
    $workStart = (int) Carbon::parse((string) settings('scheduling.default_start_time'))->format('H');
    $workEnd = (int) Carbon::parse((string) settings('scheduling.default_end_time'))->format('H');
    $timeFormat = (string) (settings('company.time_format_php') ?? 'H:i');
    $day = Carbon::parse($startDate);
    $isToday = $day->isToday();
    $staff = $this->dayColumns();
    // user_id => photo URL map, resolved once per render (not per event) so event-pane avatars can show real photos.
    $staffPhotos = $this->staff->mapWithKeys(fn ($u) => [(int) $u->id => app(\App\Services\FileService::class)->signedUrlOrNull($u->member?->icon_thumb_url)])->all();

    $lanePct = fn (int $lane, int $lanes): float => $lanes > 0 ? ($lane / $lanes) * 100 : 0;
    $widthPct = fn (int $lanes): float => $lanes > 0 ? (100 / $lanes) : 100;

    // A staff member's column shows activities they own OR participate in.
    $showsForMember = fn ($e, int $memberId): bool => $e->owner_id === $memberId
        || in_array($memberId, array_filter(array_column($e->participants, 'user_id')), true);

    // Event covers the displayed day (start date ≤ day ≤ end date). Compares local
    // calendar dates as Y-m-d strings, which is timezone-robust: instant comparisons
    // mismatch when the app tz (UTC) and display tz (Europe/London) differ.
    $coversDay = function ($e) use ($day): bool {
        if ($e->starts_at === null) {
            return false;
        }
        $dayStr = $day->toDateString();
        $startStr = Carbon::parse($e->starts_at)->toDateString();
        $endStr = $e->ends_at !== null ? Carbon::parse($e->ends_at)->toDateString() : $startStr;

        return $startStr <= $dayStr && $dayStr <= $endStr;
    };

    // "All-day" band events: activities flagged all-day by the shared detector.
    $isBandEvent = fn ($e): bool => $e->all_day && $e->starts_at !== null;
@endphp

<div class="s-cal flex-1 min-h-0">
    @php $staffPageCount = $this->dayStaffPageCount(); $totalStaff = $this->orderedDayStaff()->count(); @endphp
    @if($staffPageCount > 1)
        <div class="flex items-center justify-end gap-2 px-3 py-1" style="border-bottom: 1px solid var(--card-border); font-size: 11px;">
            <span style="color: var(--text-muted);">Users {{ $staffPage * 10 + 1 }}–{{ min(($staffPage + 1) * 10, $totalStaff) }} of {{ $totalStaff }}</span>
            <button type="button" class="s-btn s-btn-xs" wire:click="prevStaffPage" @disabled($staffPage === 0)>&lsaquo;</button>
            <button type="button" class="s-btn s-btn-xs" wire:click="nextStaffPage" @disabled($staffPage >= $staffPageCount - 1)>&rsaquo;</button>
        </div>
    @endif

    {{-- All-day band: only events flagged all-day (midnight-aligned) --}}
    @php $hasAllDay = $events->contains(fn ($e) => $isBandEvent($e) && $coversDay($e)); @endphp
    @if($hasAllDay)
        <div class="s-cal-allday">
            <div class="s-cal-allday-label">All day</div>
            <div class="flex-1 flex">
                @foreach($staff as $member)
                    @php $ownerAllDay = $events->filter(fn ($e) => $isBandEvent($e) && $coversDay($e) && $showsForMember($e, $member->id)); @endphp
                    <div class="flex-1 px-1 py-0.5 flex flex-col gap-0.5" style="min-width: 96px; border-right: 1px solid var(--card-border);" wire:key="allday-col-{{ $member->id }}">
                        @foreach($ownerAllDay as $event)
                            @php $tint = str_replace('s-avatar-', '', app(\App\Services\Calendar\OwnerColorResolver::class)->for($member->id)); @endphp
                            <div class="s-cal-allday-bar s-cal-event-{{ $tint }} {{ $event->status_id === \App\Enums\ActivityStatus::Completed->value ? 'completed' : '' }} {{ $event->status_id === \App\Enums\ActivityStatus::Cancelled->value ? 'cancelled' : '' }}"
                                 wire:key="allday-{{ $event->id }}"
                                 x-on:click="$dispatch('open-modal', 'calendar-activity-detail'); $wire.dispatch('calendar-open-detail', { activityId: {{ $event->id }} })">
                                {{ $event->subject }}
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="s-cal-grid">
        {{-- Hour gutter --}}
        <div class="s-cal-gutter">
            <div class="s-cal-gutter-spacer"></div>
            @for($h = $winStart; $h < $winEnd; $h++)
                <div class="s-cal-hour">{{ Carbon::today()->setTime($h, 0)->format($timeFormat) }}</div>
            @endfor
        </div>

        {{-- One column per visible staff member --}}
        @foreach($staff as $member)
            @php
                $timed = $events
                    ->filter(fn ($e) => ! $isBandEvent($e) && $coversDay($e) && $showsForMember($e, $member->id))
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
                $memberTint = str_replace('s-avatar-', '', app(\App\Services\Calendar\OwnerColorResolver::class)->for($member->id));
            @endphp
            <div class="s-cal-col" wire:key="day-col-{{ $member->id }}">
                <div class="s-cal-colhead">
                    <x-signals.avatar size="xs" :initials="$member->initials()" :src="$staffPhotos[$member->id] ?? null" :color="str_replace('s-avatar-', '', app(\App\Services\Calendar\OwnerColorResolver::class)->for($member->id))" />
                    <div class="text-left leading-tight">
                        <div>{{ $member->name }}</div>
                        @if($member->member?->department)
                            <div class="s-cal-colhead-dow">{{ $member->member->department }}</div>
                        @endif
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
                        $wire.dispatch('calendar-open-form', { owned_by: {{ $member->id }}, starts_at: startsAt });
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
                            $tint = $memberTint;
                        @endphp
                        <div class="s-cal-event s-cal-event-{{ $tint }} {{ $row['continues_before'] ? 's-cal-event-continue-top' : '' }} {{ $row['continues_after'] ? 's-cal-event-continue-bottom' : '' }} {{ $event->status_id === \App\Enums\ActivityStatus::Completed->value ? 'completed' : '' }} {{ $event->status_id === \App\Enums\ActivityStatus::Cancelled->value ? 'cancelled' : '' }}"
                             style="top: {{ $topPct }}%; height: {{ $heightPct }}%; left: {{ $lanePct($row['lane'], $row['lanes']) }}%; width: {{ $widthPct($row['lanes']) }}%;"
                             wire:key="day-event-{{ $event->id }}-{{ $member->id }}"
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
                                    <x-signals.avatar size="xs" :initials="$pInitials" :src="$staffPhotos[$p['user_id']] ?? null" :color="$pColor" wire:key="day-avatar-{{ $event->id }}-{{ $p['member_id'] }}" />
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

        {{-- Pad to a constant 10 columns so column width stays stable across pages --}}
        @for($i = $staff->count(); $i < 10; $i++)
            <div class="s-cal-col" wire:key="day-blank-{{ $i }}">
                <div class="s-cal-colhead"></div>
                <div class="s-cal-colbody">
                    @for($h = $winStart; $h < $winEnd; $h++)
                        <div class="s-cal-hour {{ ($h < $workStart || $h >= $workEnd) ? 's-cal-nonwork' : '' }}"></div>
                    @endfor
                </div>
            </div>
        @endfor
    </div>
</div>

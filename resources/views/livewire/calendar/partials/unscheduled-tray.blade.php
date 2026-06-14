@php
    /** @var \Illuminate\Support\Collection<int, \App\Data\Calendar\CalendarEventData> $unscheduled */
    $unscheduled = $this->unscheduledEvents;
@endphp

@if($unscheduled->isNotEmpty())
    <div class="s-cal-unscheduled" x-data="{ open: true }">
        <div class="s-cal-unscheduled-header" x-on:click="open = !open">
            <span>Unscheduled · {{ $unscheduled->count() }}</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;" x-bind:style="open ? 'transform: rotate(180deg)' : ''">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </div>
        <div class="s-cal-unscheduled-body" x-show="open" x-collapse>
            @foreach($unscheduled as $event)
                @php $tint = str_replace('s-avatar-', '', $event->owner_color); @endphp
                <div class="s-cal-unscheduled-item s-cal-chip-{{ $tint }} {{ $event->completed ? 'completed' : '' }}"
                     wire:key="unscheduled-{{ $event->id }}"
                     x-on:click="$dispatch('open-modal', 'calendar-activity-detail'); $wire.dispatch('calendar-open-detail', { activityId: {{ $event->id }} })">
                    <span class="s-cal-chip-dot"></span>
                    <span class="s-cal-unscheduled-item-title">{{ $event->subject }}</span>
                    <span class="s-cal-unscheduled-owner" style="margin-left: auto;">{{ $event->owner_name }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif

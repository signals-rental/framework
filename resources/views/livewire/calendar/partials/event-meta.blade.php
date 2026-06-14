@props(['event'])
@php
    use App\Enums\ActivityStatus;

    // Outline icon path(s) per activity type icon key (Feather-style, 24x24).
    // The icon key is carried on each "Activity Type" list value's metadata.
    $typeIcon = match ($event->type_icon) {
        'call' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'email', 'letter' => '<rect x="2" y="4" width="20" height="16"/><path d="m22 6-10 7L2 6"/>',
        'meeting' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>',
        'note' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'fax' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        default => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    };

    $statusIcon = match ($event->status_id) {
        ActivityStatus::Completed->value => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        ActivityStatus::Cancelled->value => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
        ActivityStatus::Held->value => '<circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/>',
        default => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    };

    // Priority shown as exclamation marks: Low ! · Normal !! · High !!!
    $priorityMark = str_repeat('!', max(1, $event->priority + 1));
    $priorityClass = match ($event->priority) {
        2 => 's-cal-prio-high',
        1 => 's-cal-prio-normal',
        default => 's-cal-prio-low',
    };
@endphp
<div class="s-cal-event-meta">
    <span class="s-cal-event-prio {{ $priorityClass }}">{{ $priorityMark }}</span>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><title>{{ $event->type_name }}</title>{!! $typeIcon !!}</svg>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><title>{{ $event->status_name }}</title>{!! $statusIcon !!}</svg>
    @if($event->location)
        <span class="s-cal-event-loc">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span>{{ $event->location }}</span>
        </span>
    @endif
</div>

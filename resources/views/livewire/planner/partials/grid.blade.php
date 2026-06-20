{{--
    Planner grid: a date axis across the window plus one positioned bar per
    opportunity (job). Each bar carries Delivery / In-Use / Collection sub-bands
    (positioned relative to the bar) and Customer Collecting / Customer Returning
    badges, coloured by workflow status.

    In `gantt` mode every job gets its own full-height row. In `overlap` mode the
    EventLaneAllocator has packed concurrent jobs into lanes (the `lane` / `lanes`
    keys on each row), so each cluster of overlapping jobs renders its bars in
    side-by-side horizontal lanes within a shared row band.

    All positioning is plain percentage offsets across the window — no charting
    library. Day columns are evenly spaced; the bar left/width come pre-computed
    from the component.
--}}
@php
    /** @var list<array<string, mixed>> $rows */
    $rows = $this->rows;
    /** @var list<\Illuminate\Support\Carbon> $days */
    $days = $this->dayColumns();
    $dayCount = max(1, count($days));
    $colWidth = 100 / $dayCount;

    // Lane height (px) used for overlap-mode bar stacking.
    $laneHeight = 26;
@endphp

@if(count($rows) === 0)
    <x-signals.empty
        title="{{ __('No jobs in this window') }}"
        description="{{ __('No quotes or orders fall within the selected dates and filters.') }}">
        <x-slot:icon><flux:icon.calendar-days class="!size-7" /></x-slot:icon>
    </x-signals.empty>
@else
    <div class="s-panel overflow-x-auto" wire:loading.class="opacity-60">
        <div class="s-panel-body" style="min-width: 720px;">
            {{-- Date axis --}}
            <div class="relative mb-2 flex border-b border-[var(--card-border)] pb-1">
                @foreach($days as $day)
                    <div class="shrink-0 text-center text-[10px] {{ $day->isWeekend() ? 'text-[var(--text-muted)]' : 'text-[var(--text-secondary,var(--text-muted))]' }}"
                         style="width: {{ $colWidth }}%;"
                         wire:key="day-{{ $day->toDateString() }}">
                        <div class="font-semibold">{{ $day->format('j') }}</div>
                        <div class="uppercase">{{ $day->format('D') }}</div>
                    </div>
                @endforeach
            </div>

            @if($mode === 'gantt')
                {{-- Gantt: one row per job --}}
                <div class="flex flex-col gap-2">
                    @foreach($rows as $row)
                        <div wire:key="job-{{ $row['id'] }}" class="grid grid-cols-[200px_1fr] items-center gap-3">
                            {{-- Row label --}}
                            <a href="{{ route('opportunities.show', $row['id']) }}" wire:navigate
                               class="block truncate text-[12px] hover:underline">
                                <span class="font-semibold text-[var(--text-primary)]">{{ $row['number'] ?? ('#'.$row['id']) }}</span>
                                <span class="block truncate text-[11px] text-[var(--text-muted)]">{{ $row['member'] ?? $row['subject'] }}</span>
                            </a>

                            {{-- Bar track --}}
                            <div class="relative h-9 rounded-sm bg-[var(--content-bg)]">
                                @include('livewire.planner.partials.bar', ['row' => $row, 'top' => 4, 'height' => 28])
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Overlap: lane-packed clusters in a single track --}}
                @php
                    $maxLanes = 1;
                    foreach ($rows as $r) {
                        $maxLanes = max($maxLanes, (int) ($r['lanes'] ?? 1));
                    }
                    $trackHeight = $maxLanes * $laneHeight + 6;
                @endphp
                <div class="relative" style="height: {{ $trackHeight }}px;">
                    {{-- Faint day gridlines --}}
                    @foreach($days as $i => $day)
                        <div class="absolute top-0 bottom-0 border-l border-[var(--card-border)] opacity-40"
                             style="left: {{ $i * $colWidth }}%;" wire:key="grid-{{ $day->toDateString() }}"></div>
                    @endforeach

                    @foreach($rows as $row)
                        @php
                            $lane = (int) ($row['lane'] ?? 0);
                            $top = $lane * $laneHeight + 2;
                        @endphp
                        <div wire:key="job-{{ $row['id'] }}" class="absolute left-0 right-0">
                            @include('livewire.planner.partials.bar', ['row' => $row, 'top' => $top, 'height' => $laneHeight - 4, 'withLabel' => true])
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif

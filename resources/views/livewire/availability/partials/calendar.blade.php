{{--
    Availability calendar grid: products (rows) x days (columns).

    Each cell shows the day's worst-available figure (CalendarDayData::available),
    a shortage indicator (has_shortage) and a pending-return count
    (pending_checkin). Cells are keyed by day so the grid reconciles correctly on
    Reverb-driven re-renders. Days are pre-cropped by the component's days()
    computed property; the per-product day cells are indexed by date so missing
    days (a product with no summary that day) render as "n/a".
--}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Data\Availability\CalendarProductData> $rows */
    $rows = $this->calendar;
    $dayList = $this->days;
@endphp

@if($rows->isEmpty())
    <x-signals.empty
        title="{{ __('No availability data') }}"
        description="{{ __('No products have availability snapshots at this store for the chosen window. Recalculation runs as demand is placed.') }}">
        <x-slot:icon><flux:icon.calendar class="!size-7" /></x-slot:icon>
    </x-signals.empty>
@else
    <div class="s-table-wrap overflow-x-auto" wire:loading.class="opacity-60">
        <table class="s-table s-table-compact s-table-bordered min-w-max">
            <thead>
                <tr>
                    <th class="sticky left-0 z-10 bg-[var(--card-bg)] text-left">{{ __('Product') }}</th>
                    @foreach($dayList as $day)
                        @php $carbonDay = \Illuminate\Support\Carbon::parse($day); @endphp
                        <th wire:key="head-{{ $day }}" class="px-2 text-center whitespace-nowrap {{ $carbonDay->isWeekend() ? 'bg-[var(--content-bg)]' : '' }}">
                            <div class="text-[10px] font-semibold uppercase text-[var(--text-muted)]">{{ $carbonDay->format('D') }}</div>
                            <div class="text-[12px]">{{ $carbonDay->format('j M') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php
                        // Index the product's day cells by date for O(1) lookup per column.
                        $cellsByDate = collect($row->days)->keyBy('date');
                    @endphp
                    <tr wire:key="row-{{ $row->product_id }}">
                        <td class="sticky left-0 z-10 bg-[var(--card-bg)]">
                            <button type="button" wire:click="showGantt({{ $row->product_id }})"
                                    class="s-btn-text text-left font-medium text-[var(--blue)] hover:underline"
                                    title="{{ __('Open timeline') }}">
                                {{ $row->product_name ?? __('Product #:id', ['id' => $row->product_id]) }}
                            </button>
                        </td>
                        @foreach($dayList as $day)
                            @php $cell = $cellsByDate->get($day); @endphp
                            <td wire:key="cell-{{ $row->product_id }}-{{ $day }}"
                                class="px-2 py-1 text-center align-middle">
                                @if($cell === null)
                                    <span class="text-[11px] text-[var(--text-muted)]">&middot;</span>
                                @else
                                    @php
                                        $color = $cell->has_shortage ? 'red' : ($cell->available <= 0 ? 'amber' : 'green');
                                    @endphp
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span class="s-badge s-badge-{{ $color }}" title="{{ $cell->has_shortage ? __('Shortage') : __('Worst available this day') }}">
                                            {{ $cell->available }}
                                        </span>
                                        @if($cell->pending_checkin > 0)
                                            <span class="s-badge s-badge-cyan s-badge-count" title="{{ __('Returned, awaiting check-in') }}">
                                                <flux:icon.arrow-uturn-left class="!size-2.5" /> {{ $cell->pending_checkin }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Legend --}}
    <div class="mt-3 flex flex-wrap items-center gap-4 text-[11px] text-[var(--text-muted)]">
        <span class="flex items-center gap-1.5"><span class="s-badge s-badge-green">n</span> {{ __('Available') }}</span>
        <span class="flex items-center gap-1.5"><span class="s-badge s-badge-amber">0</span> {{ __('None free') }}</span>
        <span class="flex items-center gap-1.5"><span class="s-badge s-badge-red">-n</span> {{ __('Shortage') }}</span>
        <span class="flex items-center gap-1.5"><span class="s-badge s-badge-cyan s-badge-count">n</span> {{ __('Pending check-in') }}</span>
    </div>
@endif

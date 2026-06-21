{{--
    Availability Gantt / timeline for one product at the store over the window.

    Each demand (GanttDemandBarData) is a horizontal bar split into its three
    zones — prep (period_start -> buffer_before_end), on-hire (starts_at ->
    ends_at) and turnaround (buffer_after_start -> period_end) — positioned as a
    percentage offset across the window. The bar takes the demand source's
    registered colour. Shortage windows (GanttShortageData) render as red day
    markers along a shortage track, flagged when they sit wholly in a buffer zone.

    All positioning maths is done here in PHP from the DTO's ISO timestamps; the
    bars are pure CSS/flex (no charting library).
--}}
@php
    /** @var \App\Data\Availability\GanttData|null $gantt */
    $gantt = $this->gantt;
    $windowStart = \Illuminate\Support\Carbon::parse($this->from)->startOfDay();
    $windowEnd = \Illuminate\Support\Carbon::parse($this->to)->endOfDay();
    $windowSeconds = max(1, $windowStart->diffInSeconds($windowEnd));

    // Percentage offset of a timestamp across the window, clamped to [0, 100].
    $pct = function (string $iso) use ($windowStart, $windowSeconds): float {
        $at = \Illuminate\Support\Carbon::parse($iso);
        $offset = $windowStart->diffInSeconds($at, false);

        return max(0.0, min(100.0, ($offset / $windowSeconds) * 100));
    };

    // Day tick columns across the window for the axis.
    $dayTicks = [];
    for ($d = $windowStart->copy(); $d->lessThanOrEqualTo($windowEnd); $d->addDay()) {
        $dayTicks[] = $d->copy();
    }
@endphp

<div class="mb-3 flex items-center gap-2">
    <button type="button" wire:click="showCalendar" class="s-btn s-btn-sm s-btn-ghost">
        <flux:icon.arrow-left class="!size-3.5" /> {{ __('Back to calendar') }}
    </button>
    <h2 class="text-[15px] font-semibold text-[var(--text-primary)]">
        {{ $ganttProduct?->name ?? __('Product #:id', ['id' => $ganttProductId]) }}
    </h2>
    @if($gantt !== null)
        <span class="s-badge s-badge-navy s-badge-outline">{{ __('Total stock: :n', ['n' => $gantt->total_stock]) }}</span>
    @endif
</div>

@if($gantt === null || (count($gantt->demands) === 0 && count($gantt->shortages) === 0))
    <x-signals.empty
        title="{{ __('No demand in this window') }}"
        description="{{ __('This product has no active bookings or shortages over the selected dates.') }}">
        <x-slot:icon><flux:icon.chart-bar-square class="!size-7" /></x-slot:icon>
    </x-signals.empty>
@else
    <div class="s-panel" wire:loading.class="opacity-60">
        <div class="s-panel-body">
            {{-- Date axis --}}
            <div class="relative mb-2 h-5 border-b border-[var(--card-border)]">
                @foreach($dayTicks as $tick)
                    <div class="absolute top-0 text-[10px] text-[var(--text-muted)]"
                         style="left: {{ $pct($tick->toIso8601String()) }}%;">
                        @if($tick->day === 1 || $loop->first || $tick->isMonday())
                            <span class="-ml-2 inline-block whitespace-nowrap">{{ $tick->format('j M') }}</span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Demand bars (one row each) --}}
            <div class="flex flex-col gap-1.5">
                @foreach($gantt->demands as $bar)
                    @php
                        $prepLeft = $pct($bar->period_start);
                        $hireLeft = $pct($bar->buffer_before_end);
                        $turnLeft = $pct($bar->buffer_after_start);
                        $endLeft = $pct($bar->period_end);
                        $prepW = max(0.0, $hireLeft - $prepLeft);
                        $hireW = max(0.2, $turnLeft - $hireLeft);
                        $turnW = max(0.0, $endLeft - $turnLeft);
                        $barColour = $bar->colour ?? 'var(--blue)';
                        $label = $bar->source_name ?? ($bar->source_type.' #'.$bar->source_id);
                    @endphp
                    <div wire:key="bar-{{ $bar->demand_id }}" class="relative h-6 rounded-sm bg-[var(--content-bg)]">
                        {{-- prep zone --}}
                        @if($prepW > 0)
                            <div class="absolute top-0 h-full opacity-40"
                                 style="left: {{ $prepLeft }}%; width: {{ $prepW }}%; background: {{ $barColour }};"
                                 title="{{ __('Prep / buffer-before') }}"></div>
                        @endif
                        {{-- on-hire zone --}}
                        <div class="absolute top-0 flex h-full items-center overflow-hidden rounded-sm px-1.5 text-[10px] font-medium text-white"
                             style="left: {{ $hireLeft }}%; width: {{ $hireW }}%; background: {{ $barColour }};"
                             title="{{ $label }} &middot; {{ \Illuminate\Support\Carbon::parse($bar->starts_at)->format('j M H:i') }} - {{ \Illuminate\Support\Carbon::parse($bar->ends_at)->format('j M H:i') }}">
                            <span class="truncate">{{ $bar->quantity }}&times; {{ $label }}@if($bar->asset_serial) ({{ $bar->asset_serial }})@endif</span>
                        </div>
                        {{-- turnaround zone --}}
                        @if($turnW > 0)
                            <div class="absolute top-0 h-full opacity-40"
                                 style="left: {{ $turnLeft }}%; width: {{ $turnW }}%; background: {{ $barColour }};"
                                 title="{{ __('Turnaround / buffer-after') }}"></div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Shortage windows --}}
            @if(count($gantt->shortages) > 0)
                <div class="mt-4">
                    <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Shortages') }}</div>
                    <div class="relative h-6 rounded-sm bg-[var(--content-bg)]">
                        @foreach($gantt->shortages as $shortage)
                            @php
                                $sLeft = $pct(\Illuminate\Support\Carbon::parse($shortage->from)->startOfDay()->toIso8601String());
                                $sRight = $pct(\Illuminate\Support\Carbon::parse($shortage->to)->endOfDay()->toIso8601String());
                                $sW = max(0.4, $sRight - $sLeft);
                            @endphp
                            <div wire:key="short-{{ $shortage->from }}"
                                 class="absolute top-0 h-full {{ $shortage->in_buffer_zone ? 'opacity-60' : '' }}"
                                 style="left: {{ $sLeft }}%; width: {{ $sW }}%; background: {{ $shortage->in_buffer_zone ? 'var(--amber, #f59e0b)' : 'var(--red, #ef4444)' }};"
                                 title="{{ \Illuminate\Support\Carbon::parse($shortage->from)->format('j M') }}: {{ __('short by :n', ['n' => $shortage->severity]) }}{{ $shortage->in_buffer_zone ? ' ('.__('in buffer zone').')' : '' }}"></div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-4 text-[11px] text-[var(--text-muted)]">
                        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm" style="background: var(--red);"></span> {{ __('On-hire conflict') }}</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm opacity-60" style="background: var(--amber);"></span> {{ __('In buffer zone (may be resolvable)') }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif

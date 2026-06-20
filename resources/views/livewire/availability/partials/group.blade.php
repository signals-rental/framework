{{--
    By-product-group availability grid: a group's products (rows) x dates (columns).

    Each cell shows `available (held)` — the day's worst-available figure
    (CalendarDayData::available) with the held quantity (total stock - available) in
    parentheses — colour-coded by state:
      green  = available (> warning threshold)
      amber  = warning   (low but positive)
      red    = shortage  (<= 0 with a shortage)
      cyan   = booked    (exactly 0, fully committed, no shortage)

    Rows are paginated; the cells/held are derived in the component's groupRows()
    computed property (batched per page). Cells are keyed by date so the grid
    reconciles correctly on Reverb-driven re-renders.

    The `[x]` on-sub-rent annotation slot is a Phase-4 deferral (sub-hire data is
    not yet modelled); the cell renders only the available/held pair for now.
--}}
@php
    /** @var array{rows: list<array<string, mixed>>, paginator: \Illuminate\Contracts\Pagination\LengthAwarePaginator} $grid */
    $grid = $this->groupRows;
    $rows = $grid['rows'];
    $paginator = $grid['paginator'];
    $dayList = $this->groupDays;

    $stateBadge = [
        'available' => 's-badge-green',
        'warning' => 's-badge-amber',
        'shortage' => 's-badge-red',
        'booked' => 's-badge-cyan',
    ];
@endphp

@if($storeId === 0)
    <x-signals.empty
        title="{{ __('No store selected') }}"
        description="{{ __('Choose a store to view availability.') }}">
        <x-slot:icon><flux:icon.building-storefront class="!size-7" /></x-slot:icon>
    </x-signals.empty>
@elseif($groupId === null)
    <x-signals.empty
        title="{{ __('Select a product group') }}"
        description="{{ __('Choose a product group to view the availability of its products over the period.') }}">
        <x-slot:icon><flux:icon.squares-2x2 class="!size-7" /></x-slot:icon>
    </x-signals.empty>
@elseif(empty($rows))
    <x-signals.empty
        title="{{ __('No matching products') }}"
        description="{{ __('No products in this group match the current filters, or none have availability data at this store for the chosen period.') }}">
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
                        <th wire:key="ghead-{{ $day }}" class="px-2 text-center whitespace-nowrap {{ $carbonDay->isWeekend() ? 'bg-[var(--content-bg)]' : '' }}">
                            <div class="text-[10px] font-semibold uppercase text-[var(--text-muted)]">{{ $carbonDay->format('D') }}</div>
                            <div class="text-[12px]">{{ $carbonDay->format('j M') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr wire:key="grow-{{ $row['product_id'] }}">
                        <td class="sticky left-0 z-10 bg-[var(--card-bg)]">
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="showGantt({{ $row['product_id'] }})"
                                        class="s-btn-text text-left font-medium text-[var(--blue)] hover:underline"
                                        title="{{ __('Open timeline') }}">
                                    {{ $row['product_name'] ?? __('Product #:id', ['id' => $row['product_id']]) }}
                                </button>
                                <flux:icon.information-circle class="!size-3.5 shrink-0 text-[var(--text-muted)]"
                                    title="{{ __('Cells show available (held). Open the timeline for the demand breakdown.') }}" />
                            </div>
                        </td>
                        @foreach($row['cells'] as $cell)
                            <td wire:key="gcell-{{ $row['product_id'] }}-{{ $cell['date'] }}"
                                class="px-2 py-1 text-center align-middle">
                                @if(! $cell['present'])
                                    <span class="text-[11px] text-[var(--text-muted)]">&middot;</span>
                                @else
                                    <span class="s-badge {{ $stateBadge[$cell['state']] ?? 's-badge-zinc' }}"
                                          title="{{ __(':available available, :held held', ['available' => $cell['available'], 'held' => $cell['held']]) }}">
                                        {{ $cell['available'] }}
                                        <span class="opacity-70">({{ $cell['held'] }})</span>
                                    </span>
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
        <span class="flex items-center gap-1.5"><span class="s-badge s-badge-amber">n</span> {{ __('Warning (low)') }}</span>
        <span class="flex items-center gap-1.5"><span class="s-badge s-badge-red">-n</span> {{ __('Shortage') }}</span>
        <span class="flex items-center gap-1.5"><span class="s-badge s-badge-cyan">0</span> {{ __('Booked') }}</span>
        <span>{{ __('Cell = available (held)') }}</span>
    </div>

    @if($paginator->hasPages())
        <div class="mt-4">
            {{ $paginator->links() }}
        </div>
    @endif
@endif

<?php

use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\Store;
use App\Services\Calendar\EventLaneAllocator;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/**
 * Job Planner (C1) — a calendar of OPPORTUNITIES (jobs) across dates.
 *
 * Rows are opportunities; each job draws a bar spanning its window, split into
 * Delivery / In-Use / Collection sub-bands derived from the lifecycle date fields
 * ({@see \App\Models\Opportunity}), badged with Customer Collecting / Customer
 * Returning and coloured by its workflow status. Two render modes:
 *
 *  - `gantt`   — one row per opportunity, full row height each.
 *  - `overlap` — overlapping jobs packed into horizontal lanes via the shared
 *                {@see EventLaneAllocator}, so concurrent jobs sit side-by-side.
 *
 * The page reads the opportunity projection directly (the same read model the API
 * and list views use) and gates on `opportunities.access`. All positioning maths
 * is plain PHP percentage offsets across the window — no charting library.
 */
new #[Layout('components.layouts.app')] #[Title('Job Planner')] class extends Component
{
    /** Selectable view-period lengths, in days, keyed by the URL token. */
    private const VIEW_PERIODS = [
        '1w' => 7,
        '2w' => 14,
        '1m' => 30,
        'monthly' => 30,
    ];

    /** The window start (ISO date). Defaults to today on mount. */
    #[Url(as: 'date')]
    public string $date = '';

    /** View period token: 1w / 2w / 1m / monthly. Sets the column count. */
    #[Url(as: 'view')]
    public string $viewPeriod = '2w';

    /** Render mode: `gantt` (a row each) or `overlap` (lane-packed). */
    #[Url(as: 'mode')]
    public string $mode = 'gantt';

    /** Whether quotation-state jobs are included. */
    #[Url(as: 'quotations')]
    public bool $includeQuotations = true;

    /** Whether order-state jobs are included. */
    #[Url(as: 'orders')]
    public bool $includeOrders = true;

    /** Store filter (0 = all stores). */
    #[Url(as: 'store')]
    public int $storeId = 0;

    /** Free-text search across subject / number / member name. */
    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('opportunities.access');

        if ($this->date === '') {
            $this->date = Carbon::today('UTC')->toDateString();
        }

        if (! array_key_exists($this->viewPeriod, self::VIEW_PERIODS)) {
            $this->viewPeriod = '2w';
        }

        if (! in_array($this->mode, ['gantt', 'overlap'], true)) {
            $this->mode = 'gantt';
        }
    }

    public function rendering(View $view): void
    {
        $view->title(__('Job Planner'));
    }

    /** The inclusive number of days the window spans. */
    public function windowDays(): int
    {
        return self::VIEW_PERIODS[$this->viewPeriod] ?? 14;
    }

    /** The window start, normalised to the start of the day. */
    public function windowStart(): Carbon
    {
        return Carbon::parse($this->date, 'UTC')->startOfDay();
    }

    /** The window end (inclusive), at the end of the last day. */
    public function windowEnd(): Carbon
    {
        return $this->windowStart()->copy()->addDays($this->windowDays() - 1)->endOfDay();
    }

    /** Step the window backwards by one full period. */
    public function previousPeriod(): void
    {
        $this->date = $this->windowStart()->copy()->subDays($this->windowDays())->toDateString();
    }

    /** Step the window forwards by one full period. */
    public function nextPeriod(): void
    {
        $this->date = $this->windowStart()->copy()->addDays($this->windowDays())->toDateString();
    }

    /** Jump the window back to today. */
    public function today(): void
    {
        $this->date = Carbon::today('UTC')->toDateString();
    }

    /**
     * Day-column headers across the window.
     *
     * @return list<Carbon>
     */
    public function dayColumns(): array
    {
        $days = [];
        $cursor = $this->windowStart();
        $end = $this->windowEnd();

        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    /** Stores for the filter dropdown. */
    #[Computed]
    public function stores(): Collection
    {
        return Store::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * The opportunities overlapping the window, after applying the
     * include / store / search filters, ordered by start.
     *
     * @return Collection<int, Opportunity>
     */
    #[Computed]
    public function jobs(): Collection
    {
        $states = [];
        if ($this->includeQuotations) {
            $states[] = OpportunityState::Quotation->value;
        }
        if ($this->includeOrders) {
            $states[] = OpportunityState::Order->value;
        }

        // No included state → no rows (the document-type filter selects nothing).
        if ($states === []) {
            return collect();
        }

        $from = $this->windowStart();
        $to = $this->windowEnd();

        return Opportunity::query()
            ->with(['member:id,name', 'store:id,name', 'venue:id,name'])
            ->whereIn('state', $states)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            // Overlap test: job starts on/before the window end AND ends on/after
            // the window start.
            ->where('starts_at', '<=', $to)
            ->where('ends_at', '>=', $from)
            ->when($this->storeId > 0, fn (Builder $q) => $q->where('store_id', $this->storeId))
            ->when($this->search !== '', function (Builder $q): void {
                $term = '%'.$this->search.'%';
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('subject', 'like', $term)
                        ->orWhere('number', 'like', $term)
                        ->orWhereHas('member', fn (Builder $m) => $m->where('name', 'like', $term));
                });
            })
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * The filtered jobs decorated with positioning + colour metadata, ready for
     * the view. In `overlap` mode the rows additionally carry their packed lane.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rows(): array
    {
        $windowStart = $this->windowStart();
        $windowSeconds = max(1, $windowStart->diffInSeconds($this->windowEnd()));

        $pct = function (?CarbonInterface $at) use ($windowStart, $windowSeconds): float {
            if ($at === null) {
                return 0.0;
            }

            $offset = $windowStart->diffInSeconds($at, false);

            return max(0.0, min(100.0, ($offset / $windowSeconds) * 100));
        };

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($this->jobs as $job) {
            $start = $job->starts_at;
            $end = $job->ends_at;

            $left = $pct($start);
            $right = $pct($end);
            $width = max(1.5, $right - $left);

            $status = $job->statusEnum();

            // Sub-bands: Delivery (deliver_*), In-Use (show_* or the on-hire span),
            // Collection (collect_*). Each is positioned relative to the bar itself.
            $bands = $this->bandsFor($job, $left, $width, $pct);

            $rows[] = [
                'id' => $job->id,
                'subject' => $job->subject,
                'number' => $job->number,
                'member' => $job->member?->name,
                'venue' => $job->venue?->name ?? $job->store?->name,
                'left' => $left,
                'width' => $width,
                'bands' => $bands,
                'colour' => $this->colourFor($status),
                'status_label' => $status->label(),
                'state_label' => $status->state()->label(),
                'customer_collecting' => (bool) $job->customer_collecting,
                'customer_returning' => (bool) $job->customer_returning,
                'starts_at' => $start,
                'ends_at' => $end,
                // Whole-window minute offsets for the lane allocator (overlap mode).
                'start_min' => (int) round($windowStart->diffInSeconds($start, false) / 60),
                'end_min' => (int) round($windowStart->diffInSeconds($end, false) / 60),
            ];
        }

        if ($this->mode === 'overlap') {
            $rows = app(EventLaneAllocator::class)->allocate($rows);
        }

        return $rows;
    }

    /**
     * Derive the Delivery / In-Use / Collection sub-bands for a job, expressed as
     * percentage offsets RELATIVE to the parent bar (0–100 within the bar).
     *
     * @param  callable(?CarbonInterface): float  $pct
     * @return list<array{key: string, label: string, left: float, width: float}>
     */
    private function bandsFor(Opportunity $job, float $barLeft, float $barWidth, callable $pct): array
    {
        $bands = [];

        $relative = function (?CarbonInterface $from, ?CarbonInterface $to) use ($barLeft, $barWidth, $pct): ?array {
            if ($from === null || $to === null || $barWidth <= 0) {
                return null;
            }

            $left = (($pct($from) - $barLeft) / $barWidth) * 100;
            $right = (($pct($to) - $barLeft) / $barWidth) * 100;

            return [
                'left' => max(0.0, min(100.0, $left)),
                'width' => max(0.0, min(100.0, $right) - max(0.0, $left)),
            ];
        };

        $delivery = $relative($job->deliver_starts_at, $job->deliver_ends_at);
        if ($delivery !== null && $delivery['width'] > 0) {
            $bands[] = ['key' => 'delivery', 'label' => __('Delivery')] + $delivery;
        }

        // In-Use prefers the show window, falling back to the on-hire span.
        $inUse = $relative($job->show_starts_at, $job->show_ends_at)
            ?? $relative($job->starts_at, $job->ends_at);
        if ($inUse !== null && $inUse['width'] > 0) {
            $bands[] = ['key' => 'in-use', 'label' => __('In Use')] + $inUse;
        }

        $collection = $relative($job->collect_starts_at, $job->collect_ends_at);
        if ($collection !== null && $collection['width'] > 0) {
            $bands[] = ['key' => 'collection', 'label' => __('Collection')] + $collection;
        }

        return $bands;
    }

    /**
     * Map a workflow status to an s-* colour token used for the bar + legend.
     */
    private function colourFor(OpportunityStatus $status): string
    {
        return match ($status) {
            OpportunityStatus::DraftOpen => 'var(--text-muted, #94a3b8)',
            OpportunityStatus::QuotationProvisional => 'var(--amber, #f59e0b)',
            OpportunityStatus::QuotationReserved => 'var(--violet, #8b5cf6)',
            OpportunityStatus::QuotationPostponed => 'var(--cyan, #06b6d4)',
            OpportunityStatus::QuotationLost,
            OpportunityStatus::QuotationDead => 'var(--red, #ef4444)',
            OpportunityStatus::OrderActive,
            OpportunityStatus::OrderDispatched,
            OpportunityStatus::OrderOnHire => 'var(--blue, #3b82f6)',
            OpportunityStatus::OrderReturned,
            OpportunityStatus::OrderChecked,
            OpportunityStatus::OrderComplete => 'var(--green, #22c55e)',
            OpportunityStatus::OrderCancelled => 'var(--red, #ef4444)',
        };
    }

    /**
     * The colour key / legend rows: status → label → s-* colour token.
     *
     * @return list<array{label: string, colour: string}>
     */
    #[Computed]
    public function legend(): array
    {
        $statuses = [
            OpportunityStatus::DraftOpen,
            OpportunityStatus::QuotationProvisional,
            OpportunityStatus::QuotationReserved,
            OpportunityStatus::OrderActive,
            OpportunityStatus::OrderOnHire,
            OpportunityStatus::OrderComplete,
        ];

        return array_map(fn (OpportunityStatus $s): array => [
            'label' => $s->state()->label().' · '.$s->label(),
            'colour' => $this->colourFor($s),
        ], $statuses);
    }

    /**
     * Window counts: total JOBS, plus the ORDERS / QUOTES split.
     *
     * @return array{jobs: int, orders: int, quotes: int}
     */
    #[Computed]
    public function counts(): array
    {
        $orders = $this->jobs->where('state', OpportunityState::Order)->count();
        $quotes = $this->jobs->where('state', OpportunityState::Quotation)->count();

        return [
            'jobs' => $this->jobs->count(),
            'orders' => $orders,
            'quotes' => $quotes,
        ];
    }
}; ?>

<div class="s-page">
    <x-signals.page-header title="{{ __('Job Planner') }}">
        <x-slot:icon><flux:icon.calendar-days class="!size-5" /></x-slot:icon>
        <x-slot:meta>{{ __('Schedule of quotes & orders across the planning window') }}</x-slot:meta>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="previousPeriod" class="s-btn s-btn-sm s-btn-ghost" aria-label="{{ __('Previous period') }}">
                    <flux:icon.chevron-left class="!size-4" />
                </button>
                <button type="button" wire:click="today" class="s-btn s-btn-sm s-btn-ghost">{{ __('Today') }}</button>
                <button type="button" wire:click="nextPeriod" class="s-btn s-btn-sm s-btn-ghost" aria-label="{{ __('Next period') }}">
                    <flux:icon.chevron-right class="!size-4" />
                </button>
            </div>
        </x-slot:actions>
    </x-signals.page-header>

    {{-- Count badges --}}
    <div class="mb-4 flex flex-wrap items-center gap-3" wire:loading.class="opacity-60">
        <span class="s-badge s-badge-navy">{{ __('Jobs') }} <span class="s-chip-count">{{ $this->counts['jobs'] }}</span></span>
        <span class="s-badge s-badge-blue">{{ __('Orders') }} <span class="s-chip-count">{{ $this->counts['orders'] }}</span></span>
        <span class="s-badge s-badge-amber">{{ __('Quotes') }} <span class="s-chip-count">{{ $this->counts['quotes'] }}</span></span>
    </div>

    {{-- Filters bar --}}
    <div class="s-panel mb-4">
        <div class="s-panel-body flex flex-wrap items-end gap-4">
            <div class="flex flex-col gap-1">
                <label class="s-field-label" for="planner-date">{{ __('Date') }}</label>
                <input id="planner-date" type="date" wire:model.live="date" class="s-input" />
            </div>

            <div class="flex flex-col gap-1">
                <label class="s-field-label" for="planner-view">{{ __('View') }}</label>
                <select id="planner-view" wire:model.live="viewPeriod" class="s-select">
                    <option value="1w">{{ __('1 Week') }}</option>
                    <option value="2w">{{ __('2 Weeks') }}</option>
                    <option value="1m">{{ __('1 Month') }}</option>
                    <option value="monthly">{{ __('Monthly') }}</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="s-field-label" for="planner-store">{{ __('Store') }}</label>
                <select id="planner-store" wire:model.live="storeId" class="s-select">
                    <option value="0">{{ __('All stores') }}</option>
                    @foreach($this->stores as $store)
                        <option value="{{ $store->id }}" wire:key="store-{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <span class="s-field-label">{{ __('Include') }}</span>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-1.5 text-[12px] text-[var(--text-primary)]">
                        <input type="checkbox" wire:model.live="includeQuotations" class="s-checkbox" /> {{ __('Quotations') }}
                    </label>
                    <label class="flex items-center gap-1.5 text-[12px] text-[var(--text-primary)]">
                        <input type="checkbox" wire:model.live="includeOrders" class="s-checkbox" /> {{ __('Orders') }}
                    </label>
                </div>
            </div>

            <div class="flex flex-1 flex-col gap-1" style="min-width: 180px;">
                <label class="s-field-label" for="planner-search">{{ __('Search') }}</label>
                <input id="planner-search" type="search" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('Subject, number or member') }}" class="s-input" />
            </div>

            {{-- Gantt <-> Overlap toggle --}}
            <div class="flex flex-col gap-1">
                <span class="s-field-label">{{ __('Layout') }}</span>
                <div class="inline-flex rounded-sm border border-[var(--card-border)] p-0.5">
                    <button type="button" wire:click="$set('mode', 'gantt')"
                            class="s-btn s-btn-sm {{ $mode === 'gantt' ? 's-btn-primary' : 's-btn-ghost' }}">{{ __('Gantt') }}</button>
                    <button type="button" wire:click="$set('mode', 'overlap')"
                            class="s-btn s-btn-sm {{ $mode === 'overlap' ? 's-btn-primary' : 's-btn-ghost' }}">{{ __('Overlap') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Colour key / legend --}}
    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px] text-[var(--text-muted)]">
        <span class="font-semibold uppercase tracking-wide">{{ __('Key') }}</span>
        @foreach($this->legend as $entry)
            <span class="flex items-center gap-1.5" wire:key="legend-{{ $loop->index }}">
                <span class="inline-block h-3 w-3 rounded-sm" style="background: {{ $entry['colour'] }};"></span>{{ $entry['label'] }}
            </span>
        @endforeach
    </div>

    @include('livewire.planner.partials.grid')
</div>

<?php

use App\Data\Availability\CalendarProductData;
use App\Data\Availability\GanttData;
use App\Enums\AvailabilityResolution;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Standalone Equipment Availability page (M8-4b).
 *
 * A products (rows) x days (columns) calendar grid plus a per-product Gantt
 * timeline, for a chosen store and date range. The component calls
 * {@see AvailabilityService} directly — the same service layer the
 * {@see \App\Http\Controllers\Api\V1\AvailabilityController} uses — rather than
 * HTTP-ing its own API; reads gate on `availability.view`.
 *
 * The calendar reads the pre-calculated daily-summary read model
 * ({@see AvailabilityService::getCalendar()}); kit/composed products hold no
 * summary rows (their availability is read-time) and so never appear in the grid
 * — a caveat surfaced in the UI. The Gantt reads the authoritative `demands`
 * table ({@see AvailabilityService::getGantt()}), decomposing each demand into
 * its prep / on-hire / turnaround zones.
 *
 * Reverb-live: the page subscribes to `availability.store.{storeId}` and, in
 * Gantt mode, the per-product `availability.product.{productId}.store.{storeId}`
 * channel, re-querying the grid on the `availability.changed` broadcast so the
 * view updates without a refresh (the broadcast is a light signal; the component
 * re-reads the read model for authoritative numbers).
 */
new #[Layout('components.layouts.app')] #[Title('Equipment Availability')] class extends Component
{
    use WithPagination;

    /**
     * The "warning" threshold for the product-group grid: a cell with availability
     * at or below this (but still above zero) is amber/warning, above it is green.
     * A small constant rather than a setting — it is purely a display heuristic for
     * "running low"; widen to a settings key if per-tenant tuning is ever needed.
     */
    private const WARNING_THRESHOLD = 2;

    /** The selectable period lengths (in days) for the product-group grid. */
    private const PERIOD_OPTIONS = [7, 14, 28, 30, 60];

    #[Url(as: 'store')]
    public int $storeId = 0;

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    /** @var list<int> Optional product filter for the calendar grid. */
    #[Url(as: 'products')]
    public array $productIds = [];

    /**
     * The active view: `group` (the by-product-group availability grid — the
     * default landing), `calendar` (the all-products grid) or `gantt` (per-product
     * timeline).
     */
    #[Url(as: 'view')]
    public string $viewMode = 'group';

    /** The product whose Gantt is shown when `viewMode === 'gantt'`. */
    #[Url(as: 'product')]
    public ?int $ganttProductId = null;

    // ---- by-product-group grid filters (viewMode === 'group') ----

    /** The product group whose products fill the rows. */
    #[Url(as: 'group')]
    public ?int $groupId = null;

    /** Product-type filter: `rental`, `sale` or empty (all). */
    #[Url(as: 'type')]
    public string $productType = '';

    /** Show only rows that have a shortage cell in the window. */
    #[Url(as: 'shortages')]
    public bool $shortagesOnly = false;

    /** Show only rows that have a warning (low-but-positive) cell in the window. */
    #[Url(as: 'warnings')]
    public bool $warningsOnly = false;

    /**
     * Whether quote-stage (provisional) demand counts towards held/available.
     * Wiring depends on the availability engine exposing a demand-phase filter on
     * the read model; see {@see groupRows()}.
     */
    #[Url(as: 'quotes')]
    public bool $includeQuotes = true;

    /** The window length in days for the group grid (default ~30). */
    #[Url(as: 'days')]
    public int $daysPeriod = 30;

    public function mount(): void
    {
        Gate::authorize('availability.view');

        if ($this->storeId === 0) {
            $this->storeId = (int) (Store::query()->where('is_default', true)->value('id')
                ?? Store::query()->orderBy('id')->value('id')
                ?? 0);
        }

        // Default a sensible window: today through four weeks out. The group grid
        // derives its own window from the days-period (see groupTo()), so the `to`
        // default here only matters for the calendar/gantt views.
        if ($this->from === '') {
            $this->from = Carbon::today('UTC')->toDateString();
        }

        if ($this->to === '') {
            $this->to = Carbon::today('UTC')->addWeeks(4)->toDateString();
        }

        if (! in_array($this->daysPeriod, self::PERIOD_OPTIONS, true)) {
            $this->daysPeriod = 30;
        }
    }

    /**
     * Reset pagination when a group-grid filter changes so the page never lands
     * past the (now shorter) result set.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['groupId', 'productType', 'shortagesOnly', 'warningsOnly', 'includeQuotes', 'daysPeriod', 'storeId', 'from'], true)) {
            $this->resetPage();
        }
    }

    /**
     * The selectable period lengths (in days) for the group grid.
     *
     * @return list<int>
     */
    public function periodOptions(): array
    {
        return self::PERIOD_OPTIONS;
    }

    public function rendering(View $view): void
    {
        $view->title(__('Equipment Availability'));
    }

    /**
     * Build the Echo listeners dynamically so the per-product channel is only
     * registered once a product is selected (Gantt mode). Interpolating a
     * `{ganttProductId}` placeholder via `#[On]` throws when the property is null
     * (calendar mode), so the channel strings are resolved here from the already-set
     * `storeId` / `ganttProductId` instead — the store channel is always present
     * (storeId is set in mount), the product channel is conditional. The dot-prefixed
     * custom broadcast name follows the Livewire 4 / Laravel Echo convention; the
     * Echo client bundle is wired (M8-4a).
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $listeners = [
            'echo-private:availability.store.'.$this->storeId.',.availability.changed' => 'onStoreAvailabilityChanged',
        ];

        if ($this->ganttProductId !== null) {
            $listeners['echo-private:availability.product.'.$this->ganttProductId.'.store.'.$this->storeId.',.availability.changed']
                = 'onProductAvailabilityChanged';
        }

        return $listeners;
    }

    /**
     * Re-read the read model when the store's availability changes. The broadcast
     * payload is only a light signal — the computed properties re-evaluate on the
     * next render and pull the authoritative figures from the read model.
     */
    public function onStoreAvailabilityChanged(): void
    {
        // No-op: presence of the listener triggers a re-render, which re-evaluates
        // the group/calendar/gantt computed properties against the refreshed read model.
        unset($this->groupRows, $this->calendar, $this->gantt, $this->shortageCount);
    }

    /**
     * The narrower per-product channel the Gantt binds to when one product is
     * selected. Redundant with the store channel for re-rendering, but keeps the
     * Gantt live even where the commercial store-scoping layer narrows the
     * store-wide channel's audience.
     */
    public function onProductAvailabilityChanged(): void
    {
        unset($this->gantt, $this->shortageCount);
    }

    public function showGroup(): void
    {
        $this->viewMode = 'group';
        $this->ganttProductId = null;
    }

    public function showCalendar(): void
    {
        $this->viewMode = 'calendar';
        $this->ganttProductId = null;
    }

    public function showGantt(int $productId): void
    {
        $this->viewMode = 'gantt';
        $this->ganttProductId = $productId;
    }

    public function setStore(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    /**
     * Shift the visible window by whole weeks (negative = back).
     */
    public function shiftWindow(int $weeks): void
    {
        $this->from = Carbon::parse($this->fromDate())->addWeeks($weeks)->toDateString();
        $this->to = Carbon::parse($this->toDate())->addWeeks($weeks)->toDateString();
    }

    /**
     * The ordered list of calendar day strings (Y-m-d) the grid spans, capped so
     * an over-wide range never renders an unbounded number of columns.
     *
     * @return list<string>
     */
    #[Computed]
    public function days(): array
    {
        $from = Carbon::parse($this->fromDate());
        $to = Carbon::parse($this->toDate());

        // Cap the column count defensively — the grid is a day-resolution view.
        $maxDays = 62;
        if ($from->diffInDays($to) > $maxDays) {
            $to = $from->copy()->addDays($maxDays);
        }

        $days = [];
        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    /**
     * The group grid's window end (Y-m-d): start date + the days-period, exclusive
     * of the final day count off-by-one (a 30-day period spans 30 day columns
     * starting at `from`).
     */
    public function groupTo(): string
    {
        return Carbon::parse($this->fromDate())->addDays(max(1, $this->daysPeriod) - 1)->toDateString();
    }

    /**
     * The ordered day strings (Y-m-d) the group grid spans — `from` for
     * `daysPeriod` days. Capped defensively at 62 columns like the calendar.
     *
     * @return list<string>
     */
    #[Computed]
    public function groupDays(): array
    {
        $from = Carbon::parse($this->fromDate());
        $to = Carbon::parse($this->groupTo());

        $maxDays = 62;
        if ($from->diffInDays($to) > $maxDays) {
            $to = $from->copy()->addDays($maxDays);
        }

        $days = [];
        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    /**
     * The by-product-group availability grid, paginated by product row.
     *
     * Products are the chosen group's products (optionally narrowed by
     * product-type), paginated; the page's product ids are then fed to
     * {@see AvailabilityService::getCalendar()} (which already accepts a
     * productIds filter from the daily-summary read model) for the day cells, and
     * to {@see AvailabilityService::productTotalStock()} for the per-product total
     * stock used to derive "held" (held = total stock − available). Both reads are
     * batched per page — no per-cell queries.
     *
     * Each row carries pre-classified day cells: `available`, `held`, `state`
     * (green/warning/shortage/booked). The shortages-only / warnings-only filters
     * drop rows whose window contains no shortage / no warning cell respectively.
     *
     * Include-quotes is surfaced as a control but does not yet alter the figures:
     * the daily-summary read model is computed from all active demand phases and
     * does not expose a phase filter, so excluding provisional (quote) demand would
     * require a phase-aware read path. Tracked as a follow-up.
     *
     * @return array{rows: list<array<string, mixed>>, paginator: \Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    #[Computed]
    public function groupRows(): array
    {
        $empty = ['rows' => [], 'paginator' => Product::query()->whereRaw('1 = 0')->paginate(20)];

        if ($this->storeId === 0 || $this->groupId === null) {
            return $empty;
        }

        $group = ProductGroup::query()->find($this->groupId);

        if ($group === null) {
            return $empty;
        }

        $productsQuery = $group->products()
            ->where('is_kit', false)
            ->whereNull('container_availability_mode')
            ->orderBy('name');

        $type = ProductType::tryFrom($this->productType);
        if ($type !== null) {
            $productsQuery->where('product_type', $type);
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, Product> $paginator */
        $paginator = $productsQuery->paginate(20);

        /** @var list<int> $productIds */
        $productIds = $paginator->getCollection()->map(static fn (Product $p): int => (int) $p->id)->all();

        if ($productIds === []) {
            return ['rows' => [], 'paginator' => $paginator];
        }

        $service = app(AvailabilityService::class);
        $from = Carbon::parse($this->fromDate());
        $to = Carbon::parse($this->groupTo());

        $calendar = $service->getCalendar($this->storeId, $from, $to, $productIds)->keyBy('product_id');
        $totalStock = $service->productTotalStock($productIds, $this->storeId);
        $days = $this->groupDays();

        $rows = [];

        foreach ($paginator->getCollection() as $product) {
            $productId = (int) $product->id;
            /** @var CalendarProductData|null $productCalendar */
            $productCalendar = $calendar->get($productId);
            $cellsByDate = $productCalendar !== null
                ? collect($productCalendar->days)->keyBy('date')
                : collect();

            $stock = $totalStock[$productId] ?? 0;
            $cells = [];
            $hasShortage = false;
            $hasWarning = false;

            foreach ($days as $day) {
                $cell = $cellsByDate->get($day);

                if ($cell === null) {
                    $cells[] = ['date' => $day, 'present' => false];

                    continue;
                }

                $available = $cell->available;
                $held = max(0, $stock - $available);
                $state = $this->classifyCell($available, $cell->has_shortage);

                if ($state === 'shortage') {
                    $hasShortage = true;
                }
                if ($state === 'warning') {
                    $hasWarning = true;
                }

                $cells[] = [
                    'date' => $day,
                    'present' => true,
                    'available' => $available,
                    'held' => $held,
                    'state' => $state,
                ];
            }

            if ($this->shortagesOnly && ! $hasShortage) {
                continue;
            }
            if ($this->warningsOnly && ! $hasWarning) {
                continue;
            }

            $rows[] = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'cells' => $cells,
            ];
        }

        return ['rows' => $rows, 'paginator' => $paginator];
    }

    /**
     * Classify a cell's availability into a colour state:
     *  - `shortage` (red) — availability ≤ 0 with a shortage on the day;
     *  - `booked` (teal) — availability is exactly 0 but no shortage (fully out,
     *    every unit committed, nothing oversold);
     *  - `warning` (amber) — low but still positive (0 < available ≤ threshold);
     *  - `available` (green) — comfortably available (> threshold).
     */
    /**
     * Classify an availability cell into a shared four-state badge key
     * (shortage / booked / warning / available). Used by BOTH the Calendar tab and
     * the By-Group tab so the same stock state renders the same colour in each.
     */
    public function classifyCell(int $available, bool $hasShortage): string
    {
        if ($hasShortage || $available < 0) {
            return 'shortage';
        }

        if ($available === 0) {
            return 'booked';
        }

        if ($available <= self::WARNING_THRESHOLD) {
            return 'warning';
        }

        return 'available';
    }

    /**
     * The calendar grid: one row per product, each with its ordered day cells.
     *
     * @return Collection<int, CalendarProductData>
     */
    #[Computed]
    public function calendar(): Collection
    {
        if ($this->storeId === 0) {
            return collect();
        }

        return app(AvailabilityService::class)->getCalendar(
            $this->storeId,
            Carbon::parse($this->fromDate()),
            Carbon::parse($this->toDate()),
            array_values(array_map('intval', $this->productIds)),
        );
    }

    /**
     * The Gantt read model for the selected product, or null in calendar mode /
     * when no product is selected.
     */
    #[Computed]
    public function gantt(): ?GanttData
    {
        if ($this->viewMode !== 'gantt' || $this->ganttProductId === null || $this->storeId === 0) {
            return null;
        }

        return app(AvailabilityService::class)->getGantt(
            $this->ganttProductId,
            $this->storeId,
            Carbon::parse($this->fromDate()),
            Carbon::parse($this->toDate()),
        );
    }

    /**
     * The number of product/day shortage cells in the window — drives the
     * shortage summary badge in the toolbar.
     */
    #[Computed]
    public function shortageCount(): int
    {
        if ($this->storeId === 0) {
            return 0;
        }

        return app(AvailabilityService::class)->getShortages(
            $this->storeId,
            Carbon::parse($this->fromDate()),
            Carbon::parse($this->toDate()),
        )->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $resolution = AvailabilityResolution::tryFrom(
            (string) settings('availability.resolution', AvailabilityResolution::Daily->value),
        ) ?? AvailabilityResolution::Daily;

        return [
            'stores' => Store::query()->inDefaultQueries()->orderBy('name')->get(['id', 'name']),
            'productOptions' => Product::query()
                ->where('is_kit', false)
                ->whereNull('container_availability_mode')
                ->orderBy('name')
                ->get(['id', 'name']),
            'productGroups' => ProductGroup::query()->ordered()->orderBy('name')->get(['id', 'name']),
            'productTypeOptions' => [
                ProductType::Rental->value => ProductType::Rental->label(),
                ProductType::Sale->value => ProductType::Sale->label(),
            ],
            'periodOptions' => $this->periodOptions(),
            'resolution' => $resolution,
            'ganttProduct' => $this->ganttProductId !== null
                ? Product::query()->find($this->ganttProductId)
                : null,
        ];
    }

    /**
     * Normalised window bounds (defensive — never let `to` precede `from`).
     */
    private function fromDate(): string
    {
        return $this->from !== '' ? $this->from : Carbon::today('UTC')->toDateString();
    }

    private function toDate(): string
    {
        $to = $this->to !== '' ? $this->to : Carbon::today('UTC')->addWeeks(4)->toDateString();

        return Carbon::parse($to)->lessThan(Carbon::parse($this->fromDate()))
            ? $this->fromDate()
            : $to;
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Equipment Availability">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Job Planning</span>
        </x-slot:meta>
        <x-slot:actions>
            <div class="flex items-center gap-1">
                <button type="button" wire:click="showGroup"
                        class="s-btn s-btn-sm {{ $viewMode === 'group' ? 's-btn-primary' : 's-btn-outline-blue' }}">
                    <flux:icon.squares-2x2 class="!size-3.5" /> By Group
                </button>
                <button type="button" wire:click="showCalendar"
                        class="s-btn s-btn-sm {{ $viewMode === 'calendar' ? 's-btn-primary' : 's-btn-outline-blue' }}">
                    <flux:icon.table-cells class="!size-3.5" /> Calendar
                </button>
                <button type="button"
                        @if($ganttProductId === null) disabled @endif
                        wire:click="$set('viewMode', 'gantt')"
                        class="s-btn s-btn-sm {{ $viewMode === 'gantt' ? 's-btn-primary' : 's-btn-outline-blue' }} {{ $ganttProductId === null ? 'opacity-50' : '' }}">
                    <flux:icon.chart-bar-square class="!size-3.5" /> Timeline
                </button>
            </div>
        </x-slot:actions>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
    @if($viewMode === 'group')
        {{-- ============================================================ --}}
        {{--  Toolbar (by-group): group / store / type / flags / period   --}}
        {{-- ============================================================ --}}
        <div class="s-toolbar mb-4 flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Product Group') }}</label>
                <select wire:model.live="groupId" class="s-select min-w-52" aria-label="{{ __('Product group') }}">
                    <option value="">{{ __('Select a group…') }}</option>
                    @foreach($productGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Store') }}</label>
                <select wire:model.live="storeId" class="s-select min-w-44" aria-label="{{ __('Store') }}">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Product type') }}</label>
                <select wire:model.live="productType" class="s-select min-w-32" aria-label="{{ __('Product type') }}">
                    <option value="">{{ __('All') }}</option>
                    @foreach($productTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Start date') }}</label>
                <input type="date" wire:model.live="from" class="s-input" aria-label="{{ __('Start date') }}" />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Days') }}</label>
                <select wire:model.live="daysPeriod" class="s-select min-w-24" aria-label="{{ __('Days period') }}">
                    @foreach($periodOptions as $option)
                        <option value="{{ $option }}">{{ $option }} {{ __('days') }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-4 pb-1.5">
                <label class="flex items-center gap-1.5 text-[12px]">
                    <input type="checkbox" wire:model.live="shortagesOnly" class="s-checkbox" /> {{ __('Shortages only') }}
                </label>
                <label class="flex items-center gap-1.5 text-[12px]">
                    <input type="checkbox" wire:model.live="warningsOnly" class="s-checkbox" /> {{ __('Warnings only') }}
                </label>
                <label class="flex items-center gap-1.5 text-[12px]">
                    <input type="checkbox" wire:model.live="includeQuotes" class="s-checkbox" /> {{ __('Include quotes') }}
                </label>
            </div>

            <div class="ml-auto flex items-center gap-2 pb-0.5" wire:loading.class="opacity-50">
                <span class="s-badge s-badge-zinc s-badge-outline" title="{{ __('Availability resolution') }}">{{ $resolution->label() }}</span>
            </div>
        </div>

        @include('livewire.availability.partials.group')
    @else
        {{-- ============================================================ --}}
        {{--  Toolbar: store / date-range / product filter                --}}
        {{-- ============================================================ --}}
        <div class="s-toolbar mb-4 flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">Store</label>
                <select wire:model.live="storeId" class="s-select min-w-44" aria-label="{{ __('Store') }}">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">From</label>
                <input type="date" wire:model.live="from" class="s-input" aria-label="{{ __('From date') }}" />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">To</label>
                <input type="date" wire:model.live="to" class="s-input" aria-label="{{ __('To date') }}" />
            </div>

            <div class="flex items-center gap-1 pb-0.5">
                <button type="button" wire:click="shiftWindow(-1)" class="s-btn s-btn-sm s-btn-ghost" title="{{ __('Previous week') }}" aria-label="{{ __('Previous week') }}">
                    <flux:icon.chevron-left class="!size-3.5" />
                </button>
                <button type="button" wire:click="shiftWindow(1)" class="s-btn s-btn-sm s-btn-ghost" title="{{ __('Next week') }}" aria-label="{{ __('Next week') }}">
                    <flux:icon.chevron-right class="!size-3.5" />
                </button>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">Products</label>
                <select multiple wire:model.live="productIds" class="s-select min-w-52 max-h-20" aria-label="{{ __('Filter products') }}">
                    @foreach($productOptions as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="ml-auto flex items-center gap-2 pb-0.5" wire:loading.class="opacity-50">
                @if($this->shortageCount > 0)
                    <span class="s-badge s-badge-red s-badge-dot" title="{{ __('Shortage cells in window') }}">
                        {{ $this->shortageCount }} {{ \Illuminate\Support\Str::plural('shortage', $this->shortageCount) }}
                    </span>
                @else
                    <span class="s-badge s-badge-green">No shortages</span>
                @endif
                <span class="s-badge s-badge-zinc s-badge-outline" title="{{ __('Availability resolution') }}">{{ $resolution->label() }}</span>
            </div>
        </div>

        {{-- Kit caveat: composed/kit products hold no daily-summary rows. --}}
        <p class="mb-3 flex items-center gap-1.5 text-[11px] text-[var(--text-muted)]">
            <flux:icon.information-circle class="!size-3.5 shrink-0" />
            {{ __('Kit and composed (container) products are not shown on the calendar — their availability is composed at read time. Use the per-product timeline to inspect their component demand.') }}
        </p>

        @if($storeId === 0)
            <x-signals.empty
                title="{{ __('No store selected') }}"
                description="{{ __('Choose a store to view availability.') }}">
                <x-slot:icon><flux:icon.building-storefront class="!size-7" /></x-slot:icon>
            </x-signals.empty>
        @elseif($viewMode === 'gantt')
            {{-- ==================================================== --}}
            {{--  GANTT / TIMELINE (one product)                      --}}
            {{-- ==================================================== --}}
            @include('livewire.availability.partials.gantt')
        @else
            {{-- ==================================================== --}}
            {{--  CALENDAR GRID (products x days)                     --}}
            {{-- ==================================================== --}}
            @include('livewire.availability.partials.calendar')
        @endif
    @endif
    </div>
</section>

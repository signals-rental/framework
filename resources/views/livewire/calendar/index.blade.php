<?php

use App\Data\Calendar\CalendarEventData;
use App\Models\Activity;
use App\Models\User;
use App\Services\Calendar\CalendarEventService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Calendar')] class extends Component {
    #[Url]
    public string $view = 'week';

    #[Url]
    public string $startDate = '';

    /** @var list<int> */
    #[Url]
    public array $ownerIds = [];

    /**
     * Whether the heavy events query has run. The first paint renders a skeleton
     * (fast), then `wire:init="load"` flips this so the grid queries and renders.
     */
    public bool $loaded = false;

    public int $staffPage = 0;

    private const DAY_COLUMNS_PER_PAGE = 10;

    public function mount(): void
    {
        abort_unless(Gate::allows('activities.access'), 403);

        if ($this->startDate === '') {
            $this->startDate = today()->toDateString();
        }

        if (! in_array($this->view, ['day', 'week', 'month'], true)) {
            $this->view = 'week';
        }
    }

    /**
     * Flip the loaded flag so the grid renders. Triggered by `wire:init` after the
     * initial skeleton paint, deferring the heavy events query off the first render.
     */
    public function load(): void
    {
        $this->loaded = true;
    }

    public function setView(string $view): void
    {
        if (in_array($view, ['day', 'week', 'month'], true)) {
            $this->view = $view;
        }
    }

    public function goToday(): void
    {
        $this->startDate = today()->toDateString();
    }

    public function prev(): void
    {
        $this->step(-1);
    }

    public function next(): void
    {
        $this->step(1);
    }

    public function openDayView(string $date): void
    {
        $this->startDate = $date;
        $this->view = 'day';
    }

    public function toggleOwner(int $userId): void
    {
        $allIds = $this->staff()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        // An empty filter means "all users selected" — start from that set.
        $current = $this->ownerIds === [] ? $allIds : $this->ownerIds;

        if (in_array($userId, $current, true)) {
            $current = array_values(array_filter($current, fn (int $id): bool => $id !== $userId));
        } else {
            $current = [...$current, $userId];
        }

        sort($current);
        sort($allIds);

        // Collapse back to "all" (empty) when every user is selected again.
        $this->ownerIds = ($current === $allIds) ? [] : array_values($current);
        $this->staffPage = 0;
    }

    public function clearOwners(): void
    {
        $this->ownerIds = [];
        $this->staffPage = 0;
    }

    public function prevStaffPage(): void
    {
        $this->staffPage = max(0, $this->staffPage - 1);
    }

    public function nextStaffPage(): void
    {
        $this->staffPage = min($this->dayStaffPageCount() - 1, $this->staffPage + 1);
    }

    #[On('calendar-refresh')]
    public function refresh(): void
    {
        // Re-render only; computed properties recompute and re-query events.
        unset($this->events, $this->unscheduledEvents, $this->stats);
    }

    /**
     * Step the visible window by one unit of the current view.
     */
    private function step(int $direction): void
    {
        $date = Carbon::parse($this->startDate);

        $this->startDate = match ($this->view) {
            'day' => $date->addDays($direction)->toDateString(),
            'month' => $date->addMonthsNoOverflow($direction)->toDateString(),
            default => $date->addWeeks($direction)->toDateString(),
        };
    }

    /**
     * First day of the configured week (0=Sun..6=Sat, default 1=Mon).
     */
    private function weekStart(): int
    {
        return (int) settings('preferences.first_day_of_week');
    }

    /**
     * Inclusive [from, to] window that the current view renders.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function range(): array
    {
        $date = Carbon::parse($this->startDate);

        return match ($this->view) {
            'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'month' => [
                $date->copy()->startOfMonth()->startOfWeek($this->weekStart())->startOfDay(),
                $date->copy()->endOfMonth()->endOfWeek($this->weekStartEnd())->endOfDay(),
            ],
            default => [
                $date->copy()->startOfWeek($this->weekStart())->startOfDay(),
                $date->copy()->startOfWeek($this->weekStart())->addDays(6)->endOfDay(),
            ],
        };
    }

    /**
     * The end-of-week day index paired with the configured week start.
     */
    private function weekStartEnd(): int
    {
        return ($this->weekStart() + 6) % 7;
    }

    /**
     * Visible day cursors for the week/month grids.
     *
     * @return list<CarbonInterface>
     */
    public function visibleDays(): array
    {
        [$from, $to] = $this->range();

        $days = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return Collection<int, CalendarEventData>
     */
    #[Computed]
    public function events(): Collection
    {
        [$from, $to] = $this->range();

        return app(CalendarEventService::class)
            ->scheduled($from, $to, $this->ownerIds)
            ->map(fn (Activity $activity): CalendarEventData => CalendarEventData::fromModel($activity));
    }

    /**
     * @return Collection<int, CalendarEventData>
     */
    #[Computed]
    public function unscheduledEvents(): Collection
    {
        return app(CalendarEventService::class)
            ->unscheduled($this->ownerIds)
            ->map(fn (Activity $activity): CalendarEventData => CalendarEventData::fromModel($activity));
    }

    /**
     * @return array{staff: int, activities: int, completed: int}
     */
    #[Computed]
    public function stats(): array
    {
        [$from, $to] = $this->range();
        $counts = app(CalendarEventService::class)->rangeStats($from, $to, $this->ownerIds);

        return [
            'staff' => $this->visibleStaff()->count(),
            'activities' => $counts['activities'],
            'completed' => $counts['completed'],
        ];
    }

    /**
     * Active staff used for the owner filter and day-view columns.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function staff(): Collection
    {
        return User::query()
            ->with('member')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * All active staff for the owner filter, logged-in user first.
     *
     * @return Collection<int, User>
     */
    public function orderedStaff(): Collection
    {
        $authId = auth()->id();
        $staff = $this->staff();

        return $staff->filter(fn (User $u): bool => $u->id === $authId)
            ->merge($staff->filter(fn (User $u): bool => $u->id !== $authId))
            ->values();
    }

    /**
     * Staff actually rendered as day-view columns (filtered subset, or all).
     *
     * @return Collection<int, User>
     */
    public function visibleStaff(): Collection
    {
        if ($this->ownerIds === []) {
            return $this->staff();
        }

        return $this->staff()->whereIn('id', $this->ownerIds)->values();
    }

    /**
     * Day-view columns ordered with the logged-in user first, then by name.
     *
     * @return Collection<int, User>
     */
    public function orderedDayStaff(): Collection
    {
        $authId = auth()->id();
        $staff = $this->visibleStaff();

        return $staff->filter(fn (User $u): bool => $u->id === $authId)
            ->merge($staff->filter(fn (User $u): bool => $u->id !== $authId))
            ->values();
    }

    /**
     * Number of day-view staff pages (max DAY_COLUMNS_PER_PAGE columns per page).
     */
    public function dayStaffPageCount(): int
    {
        return (int) max(1, (int) ceil($this->orderedDayStaff()->count() / self::DAY_COLUMNS_PER_PAGE));
    }

    /**
     * Staff columns shown on the current day-view page.
     *
     * @return Collection<int, User>
     */
    public function dayColumns(): Collection
    {
        $page = min($this->staffPage, $this->dayStaffPageCount() - 1);

        return $this->orderedDayStaff()
            ->slice($page * self::DAY_COLUMNS_PER_PAGE, self::DAY_COLUMNS_PER_PAGE)
            ->values();
    }

    /**
     * Working-hours window [startHour, endHour] (24h ints), auto-expanded to
     * include any visible event so nothing is clipped.
     *
     * @return array{0: int, 1: int}
     */
    public function hourWindow(): array
    {
        $start = (int) Carbon::parse((string) settings('scheduling.default_start_time'))->format('H');
        $end = (int) Carbon::parse((string) settings('scheduling.default_end_time'))->format('H');

        if ($end <= $start) {
            $end = $start + 1;
        }

        foreach ($this->events() as $event) {
            if ($event->all_day || $event->starts_at === null) {
                continue;
            }

            $eventStart = (int) Carbon::parse($event->starts_at)->format('H');
            $eventEndCarbon = $event->ends_at !== null
                ? Carbon::parse($event->ends_at)
                : Carbon::parse($event->starts_at)->addMinutes(30);
            // Round the end hour up so the trailing minutes are not clipped.
            $eventEnd = (int) $eventEndCarbon->format('H') + ($eventEndCarbon->format('i') !== '00' ? 1 : 0);

            $start = min($start, $eventStart);
            $end = max($end, $eventEnd);
        }

        return [max(0, $start), min(24, max($end, $start + 1))];
    }

    /**
     * Whether weekends should be shaded as non-working.
     */
    public function weekendShaded(): bool
    {
        return ! (bool) settings('scheduling.weekend_availability');
    }
}; ?>

<section class="w-full flex flex-col" style="min-height: 0;">
    <x-signals.page-header title="Calendar">
        <x-slot:meta>
            @if($loaded)
                <span>USERS: {{ $this->stats['staff'] }}</span>
                <span>ACTIVITIES: {{ $this->stats['activities'] }}</span>
                <span>COMPLETED: {{ $this->stats['completed'] }}</span>
            @else
                <span>USERS: <x-signals.skeleton type="rect" width="1.5rem" class="!h-3 inline-block align-middle" /></span>
                <span>ACTIVITIES: <x-signals.skeleton type="rect" width="1.5rem" class="!h-3 inline-block align-middle" /></span>
                <span>COMPLETED: <x-signals.skeleton type="rect" width="1.5rem" class="!h-3 inline-block align-middle" /></span>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <button type="button" class="s-btn" x-on:click="$dispatch('open-modal', 'calendar-feed')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg>
                iCal Feed
            </button>
            <button type="button" class="s-btn s-btn-primary"
                    x-on:click="$dispatch('open-modal', 'calendar-activity-form'); $wire.dispatch('calendar-open-form', {})">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Activity
            </button>
        </x-slot:actions>
    </x-signals.page-header>

    <x-signals.toolbar>
        <x-signals.datepicker wire:key="cal-datepicker-{{ $startDate }}" :value="$startDate" wire:model.live="startDate" x-on:input="$wire.set('startDate', $event.detail)" />

        <div class="flex items-center gap-1">
            <button type="button" class="s-toolbar-btn" wire:click="prev" aria-label="Previous">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <button type="button" class="s-btn" wire:click="goToday">Today</button>
            <button type="button" class="s-toolbar-btn" wire:click="next" aria-label="Next">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        <span class="s-toolbar-sep"></span>

        {{-- Owner filter --}}
        <div class="relative" x-data="{ open: false }">
            <button type="button" class="s-btn" x-on:click="open = !open">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Users
                @if($ownerIds !== [])
                    <span class="s-badge">{{ count($ownerIds) }}</span>
                @endif
            </button>
            <div class="s-dropdown" x-show="open" x-cloak x-on:click.outside="open = false" style="max-height: 320px; overflow-y: auto; min-width: 220px;">
                <div class="s-dropdown-item" wire:click="clearOwners">
                    <span style="font-weight: {{ $ownerIds === [] ? 700 : 400 }};">All staff</span>
                </div>
                <hr class="s-dropdown-sep">
                @foreach($this->orderedStaff() as $member)
                    <label class="s-dropdown-item" wire:key="owner-filter-{{ $member->id }}">
                        <input type="checkbox" value="{{ $member->id }}"
                               wire:click="toggleOwner({{ $member->id }})"
                               @checked($ownerIds === [] || in_array($member->id, $ownerIds, true))>
                        <x-signals.avatar
                            size="xs"
                            :initials="$member->initials()"
                            :src="app(\App\Services\FileService::class)->signedUrlOrNull($member->member?->icon_thumb_url)"
                            :color="str_replace('s-avatar-', '', app(\App\Services\Calendar\OwnerColorResolver::class)->for($member->id))"
                        />
                        <span>{{ $member->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <x-slot:right>
            {{-- Day / Week / Month switcher --}}
            <div class="s-viz-btns">
                <button type="button" class="s-viz-btn {{ $view === 'day' ? 'active' : '' }}" wire:click="setView('day')" title="Day">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18"/><line x1="4" y1="8" x2="20" y2="8"/></svg>
                </button>
                <button type="button" class="s-viz-btn {{ $view === 'week' ? 'active' : '' }}" wire:click="setView('week')" title="Week">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16"/><line x1="9" y1="4" x2="9" y2="20"/><line x1="15" y1="4" x2="15" y2="20"/></svg>
                </button>
                <button type="button" class="s-viz-btn {{ $view === 'month' ? 'active' : '' }}" wire:click="setView('month')" title="Month">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="4" x2="9" y2="22"/><line x1="15" y1="4" x2="15" y2="22"/></svg>
                </button>
            </div>
        </x-slot:right>
    </x-signals.toolbar>

    <div class="min-h-0 px-6 pb-4 max-md:px-5 max-sm:px-3 flex flex-col gap-4" wire:init="load">
        <div class="flex flex-col min-h-0" style="height: 72vh;">
            @if(! $loaded)
                {{-- First paint: skeleton only, query deferred until wire:init fires load(). --}}
                @include('livewire.calendar.partials.skeleton')
            @else
                {{-- Loaded grid; hidden and replaced by the skeleton during paging round-trips. --}}
                <div class="flex-1 min-h-0 flex flex-col" wire:loading.remove wire:target="setView,prev,next,goToday,openDayView,startDate">
                    @if($view === 'day')
                        @include('livewire.calendar.partials.day-grid')
                    @elseif($view === 'month')
                        @include('livewire.calendar.partials.month-grid')
                    @else
                        @include('livewire.calendar.partials.week-grid')
                    @endif
                </div>
                <div wire:loading.flex wire:target="setView,prev,next,goToday,openDayView,startDate" class="flex-1 min-h-0">
                    @include('livewire.calendar.partials.skeleton')
                </div>
            @endif
        </div>

        @if($loaded)
            @include('livewire.calendar.partials.unscheduled-tray')
        @endif
    </div>

    @include('livewire.calendar.partials.feed-modal')

    <livewire:calendar.activity-form-modal />
    <livewire:calendar.activity-detail-modal />
</section>

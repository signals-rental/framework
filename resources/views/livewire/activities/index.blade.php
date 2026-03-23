<?php

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Activity;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Activities')] class extends Component {
    #[Url(as: 'type')]
    public string $typeFilter = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    /** @var Collection<string, int> */
    public Collection $typeCounts;

    /** @var Collection<string, int> */
    public Collection $statusCounts;

    public int $totalCount = 0;

    public function mount(): void
    {
        $this->refreshCounts();
    }

    public function setTypeFilter(string $type): void
    {
        if ($type !== '' && ActivityType::tryFrom((int) $type) === null) {
            return;
        }

        $this->typeFilter = $type;
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ActivityStatus::tryFrom((int) $status) === null) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function deleteActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new \App\Actions\Activities\DeleteActivity)($activity);
            $this->refreshCounts();
            $this->dispatch('activity-deleted');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->refreshCounts();
            $this->dispatch('activity-deleted');
        }
    }

    public function completeActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new \App\Actions\Activities\CompleteActivity)($activity);
            $this->refreshCounts();
            $this->dispatch('activity-completed');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->refreshCounts();
            $this->dispatch('activity-completed');
        }
    }

    public function refreshCounts(): void
    {
        $this->typeCounts = Activity::query()
            ->selectRaw('type_id, count(*) as count')
            ->groupBy('type_id')
            ->pluck('count', 'type_id');

        $this->statusCounts = Activity::query()
            ->selectRaw('status_id, count(*) as count')
            ->groupBy('status_id')
            ->pluck('count', 'status_id');

        $this->totalCount = $this->typeCounts->sum();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $typeOptions = collect(ActivityType::cases())
            ->mapWithKeys(fn (ActivityType $t): array => [(string) $t->value => $t->label()])
            ->all();

        $statusOptions = collect(ActivityStatus::cases())
            ->mapWithKeys(fn (ActivityStatus $s): array => [(string) $s->value => $s->label()])
            ->all();

        return [
            'activityTypes' => [
                ActivityType::Task,
                ActivityType::Call,
                ActivityType::Meeting,
                ActivityType::Email,
                ActivityType::Note,
            ],
            'activityStatuses' => ActivityStatus::cases(),
            'totalCount' => $this->totalCount,
            'typeCounts' => $this->typeCounts,
            'statusCounts' => $this->statusCounts,
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'type_id', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'livewire.activities.partials.column-type'],
                ['key' => 'subject', 'label' => 'Subject', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text'],
                ['key' => 'regarding', 'label' => 'Regarding', 'view' => 'livewire.activities.partials.column-regarding'],
                ['key' => 'owner', 'label' => 'Owner', 'view' => 'livewire.activities.partials.column-owner'],
                ['key' => 'starts_at', 'label' => 'Starts', 'sortable' => true],
                ['key' => 'priority', 'label' => 'Priority', 'sortable' => true, 'view' => 'livewire.activities.partials.column-priority'],
                ['key' => 'status_id', 'label' => 'Status', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $statusOptions, 'view' => 'livewire.activities.partials.column-status'],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                ...($this->typeFilter !== '' ? ['ofType' => ActivityType::from((int) $this->typeFilter)] : []),
                ...($this->statusFilter !== '' ? ['ofStatus' => ActivityStatus::from((int) $this->statusFilter)] : []),
            ],
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Activities">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">CRM</span>
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        {{-- Type filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setTypeFilter('')"
                    class="s-chip {{ $typeFilter === '' ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                All <span style="opacity: 0.6;">{{ $totalCount }}</span>
            </button>
            @foreach($activityTypes as $type)
                <button wire:click="setTypeFilter('{{ $type->value }}')"
                        class="s-chip {{ $typeFilter === (string) $type->value ? 'on' : '' }}">
                    {{ $type->label() }} <span style="opacity: 0.6;">{{ $typeCounts[$type->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Status filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setStatusFilter('')" class="s-chip {{ $statusFilter === '' ? 'on' : '' }}">All Statuses</button>
            @foreach($activityStatuses as $status)
                <button wire:click="setStatusFilter('{{ $status->value }}')"
                        class="s-chip {{ $statusFilter === (string) $status->value ? 'on' : '' }}">
                    {{ $status->label() }} <span style="opacity: 0.6;">{{ $statusCounts[$status->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Data table --}}
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\Activity::class"
            :searchable="['subject']"
            :with="['owner', 'regarding']"
            :scopes="$scopes"
            :refresh-events="['activity-deleted', 'activity-completed']"
            default-sort="-created_at"
            empty-message="No activities found."
            actions-view="livewire.activities.partials.row-actions"
            toolbar-view="livewire.activities.partials.toolbar"
            entity-type="activities"
            :key="'activities-table-' . $typeFilter . '-' . $statusFilter"
        />
    </div>
</section>

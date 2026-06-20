<?php

use App\Actions\Opportunities\DeleteOpportunity;
use App\Actions\Opportunities\RestoreOpportunity;
use App\Enums\OpportunityState;
use App\Models\Opportunity;
use App\Views\OpportunityColumnRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Opportunities')] class extends Component {
    #[Url(as: 'state')]
    public string $stateFilter = '';

    #[Url(as: 'archive')]
    public string $archiveFilter = 'active';

    /** @var Collection<int, int> State value => row count. */
    public Collection $stateCounts;

    public int $totalCount = 0;

    public function mount(): void
    {
        Gate::authorize('opportunities.access');

        $state = request()->query('state', '');
        if ($state !== '' && OpportunityState::tryFrom((int) $state)) {
            $this->stateFilter = (string) $state;
        }

        $this->refreshStateCounts();
    }

    public function setStateFilter(string $state): void
    {
        if ($state !== '' && OpportunityState::tryFrom((int) $state) === null) {
            return;
        }

        $this->stateFilter = $state;
    }

    public function archiveOpportunity(int $id): void
    {
        $opportunity = Opportunity::findOrFail($id);
        (new DeleteOpportunity)($opportunity);
        $this->refreshStateCounts();
        $this->dispatch('opportunity-archived');
    }

    public function restoreOpportunity(int $id): void
    {
        $opportunity = Opportunity::withTrashed()->findOrFail($id);
        (new RestoreOpportunity)($opportunity);
        $this->refreshStateCounts();
        $this->dispatch('opportunity-restored');
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function archiveSelected(array $ids): void
    {
        $opportunities = Opportunity::whereIn('id', $ids)->get();

        DB::transaction(function () use ($opportunities): void {
            foreach ($opportunities as $opportunity) {
                (new DeleteOpportunity)($opportunity);
            }
        });

        $this->refreshStateCounts();
        $this->dispatch('opportunity-archived');
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function restoreSelected(array $ids): void
    {
        $opportunities = Opportunity::withTrashed()->whereIn('id', $ids)->get();

        DB::transaction(function () use ($opportunities): void {
            foreach ($opportunities as $opportunity) {
                (new RestoreOpportunity)($opportunity);
            }
        });

        $this->refreshStateCounts();
        $this->dispatch('opportunity-restored');
    }

    #[On('opportunity-archived')]
    #[On('opportunity-restored')]
    public function refreshStateCounts(): void
    {
        $query = match ($this->archiveFilter) {
            'archived' => Opportunity::onlyTrashed(),
            'all' => Opportunity::withTrashed(),
            default => Opportunity::query(),
        };

        $this->stateCounts = $query
            ->selectRaw('state, count(*) as count')
            ->groupBy('state')
            ->pluck('count', 'state');

        $this->totalCount = $this->stateCounts->sum();
    }

    public function setArchiveFilter(string $filter): void
    {
        if (! in_array($filter, ['active', 'archived', 'all'])) {
            return;
        }

        $this->archiveFilter = $filter;
        $this->refreshStateCounts();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $stateOptions = collect(OpportunityState::cases())
            ->mapWithKeys(fn (OpportunityState $s): array => [$s->value => $s->label()])
            ->all();

        // Derive sortable/filterable flags from the canonical OpportunityColumnRegistry
        // so the table column metadata cannot drift from the registry that drives
        // custom views and the column picker. Only columns mapping 1:1 to a real
        // `opportunities` column take their flags from the registry.
        $registry = app(OpportunityColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)?->sortable ?? false,
            'filterable' => $registry->get($key)?->filterable ?? false,
        ];

        return [
            'opportunityStates' => [
                OpportunityState::Draft,
                OpportunityState::Quotation,
                OpportunityState::Order,
            ],
            'totalCount' => $this->totalCount,
            'stateCounts' => $this->stateCounts,
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'subject', 'label' => 'Subject', ...$columnMeta('subject'), 'filter_type' => 'text', 'view' => 'livewire.opportunities.partials.column-subject'],
                ['key' => 'reference', 'label' => 'Reference', ...$columnMeta('reference'), 'filter_type' => 'text', 'view' => 'livewire.opportunities.partials.column-reference'],
                ['key' => 'state', 'label' => 'State', ...$columnMeta('state'), 'filter_type' => 'select', 'filter_options' => $stateOptions, 'view' => 'livewire.opportunities.partials.column-state'],
                ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'view' => 'livewire.opportunities.partials.column-status'],
                ['key' => 'starts_at', 'label' => 'Starts', ...$columnMeta('starts_at'), 'view' => 'livewire.opportunities.partials.column-starts-at'],
                ['key' => 'ends_at', 'label' => 'Ends', ...$columnMeta('ends_at'), 'view' => 'livewire.opportunities.partials.column-ends-at'],
                ['key' => 'charge_total', 'label' => 'Charge Total', ...$columnMeta('charge_total'), 'view' => 'livewire.opportunities.partials.column-charge-total'],
                ['key' => 'created_at', 'label' => 'Created', ...$columnMeta('created_at'), 'view' => 'livewire.opportunities.partials.column-created'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                ...($this->stateFilter !== '' ? ['ofState' => OpportunityState::from((int) $this->stateFilter)] : []),
                ...match ($this->archiveFilter) {
                    'archived' => ['archived' => true],
                    'all' => ['withArchived' => true],
                    default => [],
                },
            ],
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Opportunities">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Job Planning</span>
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        {{-- State filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setStateFilter('')"
                    class="s-chip {{ $stateFilter === '' ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                All <span class="s-chip-count">{{ $totalCount }}</span>
            </button>
            @foreach($opportunityStates as $state)
                <button wire:click="setStateFilter('{{ $state->value }}')"
                        class="s-chip {{ $stateFilter === (string) $state->value ? 'on' : '' }}">
                    {{ $state->label() }} <span class="s-chip-count">{{ $stateCounts[$state->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Archive filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setArchiveFilter('active')" class="s-chip {{ $archiveFilter === 'active' ? 'on' : '' }}">Active</button>
            <button wire:click="setArchiveFilter('archived')" class="s-chip {{ $archiveFilter === 'archived' ? 'on' : '' }}">Archived</button>
            <button wire:click="setArchiveFilter('all')" class="s-chip {{ $archiveFilter === 'all' ? 'on' : '' }}">All</button>
        </div>

        {{-- Data table --}}
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\Opportunity::class"
            :searchable="['subject', 'number', 'reference']"
            :with="['member', 'store']"
            :scopes="$scopes"
            :refresh-events="['opportunity-archived', 'opportunity-restored']"
            default-sort="-created_at"
            empty-message="No opportunities found."
            actions-view="livewire.opportunities.partials.row-actions"
            bulk-actions-view="livewire.opportunities.partials.bulk-actions"
            toolbar-view="livewire.opportunities.partials.toolbar"
            entity-type="opportunities"
            :key="'opportunities-table-' . $stateFilter . '-' . $archiveFilter"
        />
    </div>
</section>

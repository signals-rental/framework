<?php

use App\Actions\Members\ArchiveMember;
use App\Actions\Members\RestoreMember;
use App\Enums\MembershipType;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Members')] class extends Component {
    #[Url(as: 'type')]
    public string $typeFilter = '';

    #[Url(as: 'archive')]
    public string $archiveFilter = 'active';

    /** @var Collection<string, int> */
    public Collection $typeCounts;

    public int $totalCount = 0;

    public function mount(): void
    {
        $type = request()->query('type', '');
        if ($type !== '' && MembershipType::tryFrom($type)) {
            $this->typeFilter = $type;
        }

        $this->refreshTypeCounts();
    }

    public function setTypeFilter(string $type): void
    {
        if ($type !== '' && MembershipType::tryFrom($type) === null) {
            return;
        }

        $this->typeFilter = $type;
    }

    public function archiveMember(int $memberId): void
    {
        $member = Member::findOrFail($memberId);
        (new ArchiveMember)($member);
        $this->refreshTypeCounts();
        $this->dispatch('member-archived');
    }

    public function restoreMember(int $memberId): void
    {
        $member = Member::withTrashed()->findOrFail($memberId);
        (new RestoreMember)($member);
        $this->refreshTypeCounts();
        $this->dispatch('member-restored');
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function archiveSelected(array $ids): void
    {
        $members = Member::whereIn('id', $ids)->get();

        DB::transaction(function () use ($members): void {
            foreach ($members as $member) {
                (new ArchiveMember)($member);
            }
        });

        $this->refreshTypeCounts();
        $this->dispatch('member-archived');
    }

    #[On('member-archived')]
    #[On('member-restored')]
    public function refreshTypeCounts(): void
    {
        $query = match ($this->archiveFilter) {
            'archived' => Member::onlyTrashed(),
            'all' => Member::withTrashed(),
            default => Member::query(),
        };

        $this->typeCounts = $query
            ->selectRaw('membership_type, count(*) as count')
            ->groupBy('membership_type')
            ->pluck('count', 'membership_type');

        $this->totalCount = $this->typeCounts->sum();
    }

    public function setArchiveFilter(string $filter): void
    {
        if (! in_array($filter, ['active', 'archived', 'all'])) {
            return;
        }

        $this->archiveFilter = $filter;
        $this->refreshTypeCounts();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $typeOptions = collect(MembershipType::cases())
            ->mapWithKeys(fn (MembershipType $t): array => [$t->value => $t->label()])
            ->all();

        return [
            'membershipTypes' => [
                MembershipType::Organisation,
                MembershipType::Venue,
                MembershipType::Contact,
            ],
            'totalCount' => $this->totalCount,
            'typeCounts' => $this->typeCounts,
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'avatar', 'label' => '', 'view' => 'livewire.members.partials.column-avatar'],
                ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'livewire.members.partials.column-name'],
                ['key' => 'membership_type', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'livewire.members.partials.column-type'],
                ['key' => 'email', 'label' => 'Primary Email', 'view' => 'livewire.members.partials.column-email'],
                ['key' => 'phone', 'label' => 'Primary Phone', 'view' => 'livewire.members.partials.column-phone'],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => ['1' => 'Active', '0' => 'Inactive'], 'view' => 'livewire.partials.column-active-status'],
                ['key' => 'tag_list', 'label' => 'Tags', 'view' => 'livewire.members.partials.column-tags'],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true, 'view' => 'livewire.members.partials.column-created'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                ...($this->typeFilter !== '' ? ['ofType' => MembershipType::from($this->typeFilter)] : []),
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
    <x-signals.page-header title="Members">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">People &amp; Places</span>
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        {{-- Type filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setTypeFilter('')"
                    class="s-chip {{ $typeFilter === '' ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                All <span style="opacity: 0.6;">{{ $totalCount }}</span>
            </button>
            @foreach($membershipTypes as $type)
                <button wire:click="setTypeFilter('{{ $type->value }}')"
                        class="s-chip {{ $typeFilter === $type->value ? 'on' : '' }}">
                    @if($type === \App\Enums\MembershipType::Contact)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                    @elseif($type === \App\Enums\MembershipType::Organisation)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M3 21h18"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M9 16h1"/><path d="M14 8h1"/><path d="M14 12h1"/><path d="M14 16h1"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/></svg>
                    @elseif($type === \App\Enums\MembershipType::Venue)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    @endif
                    {{ $type->label() }} <span style="opacity: 0.6;">{{ $typeCounts[$type->value] ?? 0 }}</span>
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
            :model="\App\Models\Member::class"
            :searchable="['name']"
            :with="['emails', 'phones']"
            :with-counts="['addresses', 'emails', 'phones', 'links']"
            :scopes="$scopes"
            :refresh-events="['member-archived', 'member-restored', 'member-merged']"
            default-sort="name"
            empty-message="No members found."
            actions-view="livewire.members.partials.row-actions"
            bulk-actions-view="livewire.members.partials.bulk-actions"
            toolbar-view="livewire.members.partials.toolbar"
            entity-type="members"
            :key="'members-table-' . $typeFilter . '-' . $archiveFilter"
        />
    </div>

    <livewire:members.merge-modal />
</section>

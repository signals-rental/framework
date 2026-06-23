<?php

use App\Actions\Opportunities\AddOpportunityParticipant;
use App\Actions\Opportunities\RemoveOpportunityParticipant;
use App\Actions\Opportunities\UpdateOpportunityParticipant;
use App\Data\Opportunities\AddOpportunityParticipantData;
use App\Data\Opportunities\UpdateOpportunityParticipantData;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityParticipant;
use App\Services\Api\RansackFilter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Livewire\Concerns\HasOpportunityActions;

/**
 * Manage the members associated with an opportunity, each in a named role
 * (the RMS `participants[]` shape — C3f).
 *
 * Participants are plain, non-event-sourced CRM associations. The panel lets an
 * editor attach a member (organisation or contact), pick a role from the
 * suggested set (free-text-backed), toggle mute, and remove participants. Every
 * mutation routes through the same action classes the API uses, so the
 * business-logic path is shared.
 */
new #[Layout('components.layouts.app')] class extends Component
{
    use HasOpportunityActions;

    /**
     * The free-text roles the UI suggests (the column is a plain string, so any
     * value is accepted by the action).
     *
     * @var list<string>
     */
    public const SUGGESTED_ROLES = [
        'Primary contact',
        'Secondary contact',
        'Account manager',
        'Site contact',
    ];

    public Opportunity $opportunity;

    public string $memberSearch = '';

    public ?int $memberId = null;

    public ?string $memberSelectedName = null;

    public string $role = '';

    public bool $mute = false;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Participants');
    }

    /**
     * Search organisation/contact members not already attached, for the picker.
     *
     * @return Collection<int, Member>
     */
    public function getMemberOptionsProperty(): Collection
    {
        if (trim($this->memberSearch) === '') {
            return collect();
        }

        $like = Member::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return Member::query()
            ->where('is_active', true)
            ->whereIn('membership_type', [MembershipType::Organisation, MembershipType::Contact])
            ->whereNotIn('id', $this->opportunity->participants()->pluck('member_id'))
            ->where('name', $like, '%'.RansackFilter::escapeLike($this->memberSearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'membership_type']);
    }

    /**
     * The opportunity's current participants, member eager-loaded.
     *
     * @return Collection<int, OpportunityParticipant>
     */
    public function getParticipantsProperty(): Collection
    {
        return $this->opportunity->participants()->with('member')->get();
    }

    public function selectMember(int $id): void
    {
        $member = Member::query()
            ->where('is_active', true)
            ->whereIn('membership_type', [MembershipType::Organisation, MembershipType::Contact])
            ->find($id);

        if ($member === null) {
            return;
        }

        $this->memberId = $member->id;
        $this->memberSelectedName = $member->name;
        $this->memberSearch = '';
    }

    public function clearMember(): void
    {
        $this->memberId = null;
        $this->memberSelectedName = null;
        $this->memberSearch = '';
    }

    public function add(): void
    {
        Gate::authorize('opportunities.edit');

        if ($this->memberId === null) {
            throw ValidationException::withMessages([
                'memberId' => 'Select a member to add.',
            ]);
        }

        $data = AddOpportunityParticipantData::from([
            'member_id' => $this->memberId,
            'role' => trim($this->role) === '' ? null : trim($this->role),
            'mute' => $this->mute,
        ]);

        (new AddOpportunityParticipant)($this->opportunity, $data);

        $this->reset(['memberId', 'memberSelectedName', 'memberSearch', 'role', 'mute']);
    }

    public function updateRole(int $participantId, string $role): void
    {
        $participant = $this->resolveParticipant($participantId);

        (new UpdateOpportunityParticipant)(
            $participant,
            UpdateOpportunityParticipantData::from(['role' => trim($role) === '' ? null : trim($role)]),
        );
    }

    public function toggleMute(int $participantId): void
    {
        $participant = $this->resolveParticipant($participantId);

        (new UpdateOpportunityParticipant)(
            $participant,
            UpdateOpportunityParticipantData::from(['mute' => ! $participant->mute]),
        );
    }

    public function remove(int $participantId): void
    {
        $participant = $this->resolveParticipant($participantId);

        (new RemoveOpportunityParticipant)($participant);
    }

    private function resolveParticipant(int $participantId): OpportunityParticipant
    {
        return $this->opportunity->participants()->findOrFail($participantId);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return $this->opportunityActionData();
    }
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Participants', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'participants'])

    @php($canEdit = \Illuminate\Support\Facades\Gate::allows('opportunities.edit'))

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 space-y-4">
        @if($canEdit)
            <x-signals.panel title="Add participant">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    {{-- Member picker --}}
                    <div class="md:col-span-5">
                        <label class="block text-sm font-medium mb-1">Member</label>
                        @if($memberSelectedName)
                            <div class="flex items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-secondary)] px-3 py-2">
                                <span class="flex-1 truncate">{{ $memberSelectedName }}</span>
                                <button type="button" wire:click="clearMember" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] shrink-0">&times;</button>
                            </div>
                        @else
                            <div x-data="{ open: false }" x-on:click.away="open = false" class="relative">
                                <flux:input
                                    wire:model.live.debounce.300ms="memberSearch"
                                    placeholder="Search organisations or contacts..."
                                    x-on:focus="open = true"
                                    x-on:input="open = true"
                                    autocomplete="off"
                                />
                                @if($this->memberOptions->isNotEmpty())
                                    <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg-primary)] shadow-lg max-h-60 overflow-y-auto">
                                        @foreach($this->memberOptions as $option)
                                            <button
                                                type="button"
                                                wire:key="member-option-{{ $option->id }}"
                                                wire:click="selectMember({{ $option->id }})"
                                                x-on:click="open = false"
                                                class="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--bg-secondary)] transition-colors"
                                            >
                                                {{ $option->name }}
                                                <span class="text-[var(--text-muted)]">· {{ $option->membership_type->label() }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                        @error('memberId') <p class="mt-1 text-sm text-[var(--red)]">{{ $message }}</p> @enderror
                        @error('member_id') <p class="mt-1 text-sm text-[var(--red)]">{{ $message }}</p> @enderror
                    </div>

                    {{-- Role (free-text, suggested set via datalist) --}}
                    <div class="md:col-span-4">
                        <flux:input
                            wire:model="role"
                            label="Role"
                            placeholder="e.g. Primary contact"
                            list="participant-role-suggestions"
                            maxlength="100"
                        />
                        <datalist id="participant-role-suggestions">
                            @foreach(self::SUGGESTED_ROLES as $suggested)
                                <option value="{{ $suggested }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    {{-- Mute --}}
                    <div class="md:col-span-2 flex items-center h-10">
                        <flux:checkbox wire:model="mute" label="Mute" />
                    </div>

                    <div class="md:col-span-1">
                        <flux:button wire:click="add" variant="primary" class="w-full">Add</flux:button>
                    </div>
                </div>
            </x-signals.panel>
        @endif

        @if($this->participants->isNotEmpty())
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Role</th>
                            <th>Mute</th>
                            @if($canEdit)<th class="text-right">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->participants as $participant)
                            <tr wire:key="participant-{{ $participant->id }}">
                                <td>{{ $participant->member?->name ?? '—' }}</td>
                                <td>
                                    @if($canEdit)
                                        <flux:input
                                            type="text"
                                            value="{{ $participant->role }}"
                                            wire:change="updateRole({{ $participant->id }}, $event.target.value)"
                                            placeholder="No role"
                                            list="participant-role-suggestions"
                                            maxlength="100"
                                            class="max-w-xs"
                                        />
                                    @else
                                        {{ $participant->role ?? '—' }}
                                    @endif
                                </td>
                                <td>
                                    @if($participant->mute)
                                        <span class="s-badge s-badge-amber">Muted</span>
                                    @else
                                        <span class="text-[var(--text-muted)]">—</span>
                                    @endif
                                </td>
                                @if($canEdit)
                                    <td class="text-right whitespace-nowrap">
                                        <flux:button size="sm" variant="ghost" wire:click="toggleMute({{ $participant->id }})">
                                            {{ $participant->mute ? 'Unmute' : 'Mute' }}
                                        </flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            wire:click="remove({{ $participant->id }})"
                                            wire:confirm="Remove this participant?"
                                        >Remove</flux:button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-signals.table-wrap>
        @else
            <x-signals.empty
                title="No participants"
                description="No members are associated with this opportunity yet."
            />
        @endif
    </div>
    @include('livewire.opportunities.partials.opportunity-action-modals')
</section>

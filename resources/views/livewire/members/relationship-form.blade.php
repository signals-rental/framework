<?php

use App\Actions\Members\CreateMemberRelationship;
use App\Data\Members\CreateMemberRelationshipData;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\MemberRelationship;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;
    public ?int $relatedMemberId = null;
    public string $relationshipType = '';
    public bool $isPrimary = false;

    public function mount(Member $member): void
    {
        $this->member = $member;
    }

    public function save(): void
    {
        $this->validate([
            'relatedMemberId' => ['required', 'integer', 'exists:members,id'],
            'relationshipType' => ['nullable', 'string', 'max:255'],
            'isPrimary' => ['boolean'],
        ]);

        $isContact = $this->member->membership_type === MembershipType::Contact;

        if ($isContact) {
            $ownerMember = $this->member;
            $relatedMemberId = $this->relatedMemberId;
        } else {
            $ownerMember = Member::findOrFail($this->relatedMemberId);
            $relatedMemberId = $this->member->id;
        }

        DB::transaction(function () use ($isContact, $ownerMember, $relatedMemberId) {
            if ($this->isPrimary && $isContact) {
                MemberRelationship::query()
                    ->where('member_id', $this->member->id)
                    ->update(['is_primary' => false]);
            }

            (new CreateMemberRelationship)($ownerMember, CreateMemberRelationshipData::from([
                'related_member_id' => $relatedMemberId,
                'relationship_type' => $this->relationshipType ?: null,
                'is_primary' => $this->isPrimary,
            ]));
        });

        $this->redirect(route('members.relationships', $this->member), navigate: true);
    }

    public function with(): array
    {
        $isContact = $this->member->membership_type === MembershipType::Contact;

        if ($isContact) {
            $eligibleMembers = Member::query()
                ->where('membership_type', MembershipType::Organisation)
                ->where('is_active', true)
                ->where('id', '!=', $this->member->id)
                ->orderBy('name')
                ->get();
        } else {
            $eligibleMembers = Member::query()
                ->where('membership_type', MembershipType::Contact)
                ->where('is_active', true)
                ->where('id', '!=', $this->member->id)
                ->orderBy('name')
                ->get();
        }

        return [
            'isContact' => $isContact,
            'eligibleMembers' => $eligibleMembers,
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Add Relationship">
        <x-slot:breadcrumbs>
            <a href="{{ route('members.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Members</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('members.show', $member) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $member->name }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('members.relationships', $member) }}" wire:navigate class="text-[var(--link)] hover:underline">Relationships</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>Add</span>
        </x-slot:breadcrumbs>
    </x-signals.page-header>

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <form wire:submit="save" class="max-w-2xl space-y-8">
            <x-signals.form-section title="Relationship Details">
                <div class="space-y-4">
                    <flux:select wire:model="relatedMemberId" label="{{ $isContact ? 'Organisation' : 'Contact' }}" required>
                        <option value="">Select {{ $isContact ? 'an organisation' : 'a contact' }}...</option>
                        @foreach($eligibleMembers as $eligible)
                            <option value="{{ $eligible->id }}">{{ $eligible->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="relationshipType" label="Relationship Type" placeholder="e.g. Employee, Contractor, Director" />
                    @if($isContact)
                        <flux:checkbox wire:model="isPrimary" label="Primary organisation" />
                    @endif
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Add Relationship</flux:button>
                <flux:button variant="ghost" href="{{ route('members.relationships', $member) }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </div>
</section>

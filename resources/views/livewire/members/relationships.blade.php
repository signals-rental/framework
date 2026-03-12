<?php

use App\Actions\Members\DeleteMemberRelationship;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\MemberRelationship;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function deleteRelationship(int $relationshipId): void
    {
        $relationship = MemberRelationship::query()
            ->where(function ($query) use ($relationshipId) {
                $query->where('id', $relationshipId)
                    ->where(function ($q) {
                        $q->where('member_id', $this->member->id)
                            ->orWhere('related_member_id', $this->member->id);
                    });
            })
            ->firstOrFail();

        (new DeleteMemberRelationship)($relationship);
    }

    public function with(): array
    {
        $isContact = $this->member->membership_type === MembershipType::Contact;

        if ($isContact) {
            $relationships = MemberRelationship::query()
                ->where('member_id', $this->member->id)
                ->with('relatedMember')
                ->orderBy('is_primary', 'desc')
                ->get();
        } else {
            $relationships = MemberRelationship::query()
                ->where('related_member_id', $this->member->id)
                ->with('member')
                ->orderBy('is_primary', 'desc')
                ->get();
        }

        return [
            'relationships' => $relationships,
            'isContact' => $isContact,
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$member->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('members.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Members</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('members.show', $member) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $member->name }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>Relationships</span>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('members.relationships.create', $member) }}" wire:navigate>Add Relationship</flux:button>
        </x-slot:actions>
    </x-signals.page-header>

    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'relationships'])

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>{{ $isContact ? 'Organisation' : 'Contact' }}</th>
                        <th>Relationship Type</th>
                        <th>Primary</th>
                        <th class="w-[100px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($relationships as $relationship)
                        @php
                            $relatedMember = $isContact ? $relationship->relatedMember : $relationship->member;
                        @endphp
                        <tr wire:key="relationship-{{ $relationship->id }}">
                            <td class="font-medium">
                                <a href="{{ route('members.show', $relatedMember) }}" wire:navigate class="text-[var(--link)] hover:underline">
                                    {{ $relatedMember->name }}
                                </a>
                                <span class="ml-1 s-badge s-badge-blue">{{ $relatedMember->membership_type->label() }}</span>
                            </td>
                            <td>{{ $relationship->relationship_type ?? '—' }}</td>
                            <td>
                                @if($relationship->is_primary)
                                    <span class="s-badge s-badge-green">Primary</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <button wire:click="deleteRelationship({{ $relationship->id }})"
                                        wire:confirm="Are you sure you want to remove this relationship?"
                                        class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-[var(--text-muted)]">No relationships found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

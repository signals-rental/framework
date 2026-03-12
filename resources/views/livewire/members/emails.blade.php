<?php

use App\Actions\Members\DeleteEmail;
use App\Models\Member;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function deleteEmail(int $emailId): void
    {
        $email = $this->member->emails()->findOrFail($emailId);
        (new DeleteEmail)($email);
        $this->member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function with(): array
    {
        return [
            'emails' => $this->member->emails()->with('type')->orderBy('is_primary', 'desc')->get(),
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
            <span>Emails</span>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('members.emails.create', $member) }}" wire:navigate>Add Email</flux:button>
        </x-slot:actions>
    </x-signals.page-header>

    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'emails'])

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Address</th>
                        <th>Type</th>
                        <th>Primary</th>
                        <th class="w-[100px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($emails as $email)
                        <tr wire:key="email-{{ $email->id }}">
                            <td class="font-medium">{{ $email->address }}</td>
                            <td>{{ $email->type?->name ?? '—' }}</td>
                            <td>
                                @if($email->is_primary)
                                    <span class="s-badge s-badge-green">Primary</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('members.emails.edit', [$member, $email]) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                    <flux:icon.pencil-square class="w-4 h-4" />
                                </a>
                                <button wire:click="deleteEmail({{ $email->id }})"
                                        wire:confirm="Are you sure you want to delete this email?"
                                        class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-[var(--text-muted)]">No emails found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

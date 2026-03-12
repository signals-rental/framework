<?php

use App\Actions\Members\DeleteLink;
use App\Models\Member;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function deleteLink(int $linkId): void
    {
        $link = $this->member->links()->findOrFail($linkId);
        (new DeleteLink)($link);
        $this->member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function with(): array
    {
        return [
            'links' => $this->member->links()->with('type')->orderBy('name')->get(),
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
            <span>Links</span>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('members.links.create', $member) }}" wire:navigate>Add Link</flux:button>
        </x-slot:actions>
    </x-signals.page-header>

    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'links'])

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="w-[100px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($links as $link)
                        <tr wire:key="link-{{ $link->id }}">
                            <td class="font-medium">
                                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="text-[var(--link)] hover:underline">
                                    {{ Str::limit($link->url, 50) }}
                                </a>
                            </td>
                            <td>{{ $link->name ?? '—' }}</td>
                            <td>{{ $link->type?->name ?? '—' }}</td>
                            <td class="text-right">
                                <a href="{{ route('members.links.edit', [$member, $link]) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                    <flux:icon.pencil-square class="w-4 h-4" />
                                </a>
                                <button wire:click="deleteLink({{ $link->id }})"
                                        wire:confirm="Are you sure you want to delete this link?"
                                        class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-[var(--text-muted)]">No links found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

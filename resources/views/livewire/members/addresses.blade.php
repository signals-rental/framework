<?php

use App\Actions\Members\DeleteAddress;
use App\Models\Member;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function deleteAddress(int $addressId): void
    {
        $address = $this->member->addresses()->findOrFail($addressId);
        (new DeleteAddress)($address);
        $this->member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function with(): array
    {
        return [
            'addresses' => $this->member->addresses()->with(['country', 'type'])->orderBy('is_primary', 'desc')->get(),
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
            <span>Addresses</span>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('members.addresses.create', $member) }}" wire:navigate>Add Address</flux:button>
        </x-slot:actions>
    </x-signals.page-header>

    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'addresses'])

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Street</th>
                        <th>City</th>
                        <th>Postcode</th>
                        <th>Country</th>
                        <th>Type</th>
                        <th>Primary</th>
                        <th class="w-[100px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($addresses as $address)
                        <tr wire:key="address-{{ $address->id }}">
                            <td class="font-medium">{{ $address->name ?? '—' }}</td>
                            <td>{{ Str::limit($address->street, 40) ?? '—' }}</td>
                            <td>{{ $address->city ?? '—' }}</td>
                            <td>{{ $address->postcode ?? '—' }}</td>
                            <td>{{ $address->country?->name ?? '—' }}</td>
                            <td>{{ $address->type?->name ?? '—' }}</td>
                            <td>
                                @if($address->is_primary)
                                    <span class="s-badge s-badge-green">Primary</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('members.addresses.edit', [$member, $address]) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                    <flux:icon.pencil-square class="w-4 h-4" />
                                </a>
                                <button wire:click="deleteAddress({{ $address->id }})"
                                        wire:confirm="Are you sure you want to delete this address?"
                                        class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-[var(--text-muted)]">No addresses found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

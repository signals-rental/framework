<?php

use App\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links']);
        $this->member->load(['organisationTaxClass', 'organisations', 'contacts']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name);
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$member->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('members.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Members</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $member->name }}</span>
        </x-slot:breadcrumbs>
        <x-slot:meta>
            <span class="s-badge s-badge-blue">{{ $member->membership_type->label() }}</span>
            @if($member->is_active)
                <span class="s-badge s-badge-green">Active</span>
            @else
                <span class="s-badge s-badge-zinc">Inactive</span>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('members.edit', $member) }}" wire:navigate>Edit</flux:button>
        </x-slot:actions>
    </x-signals.page-header>

    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'overview'])

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <div class="grid grid-cols-2 gap-8 max-md:grid-cols-1">
            <x-signals.form-section title="Details">
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Name</dt>
                        <dd class="mt-0.5">{{ $member->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Type</dt>
                        <dd class="mt-0.5">{{ $member->membership_type->label() }}</dd>
                    </div>
                    @if($member->description)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Description</dt>
                            <dd class="mt-0.5">{{ $member->description }}</dd>
                        </div>
                    @endif
                    @if($member->default_currency_code)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Default Currency</dt>
                            <dd class="mt-0.5">{{ $member->default_currency_code }}</dd>
                        </div>
                    @endif
                    @if($member->locale)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Locale</dt>
                            <dd class="mt-0.5">{{ $member->locale }}</dd>
                        </div>
                    @endif
                    @if($member->organisationTaxClass)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Tax Class</dt>
                            <dd class="mt-0.5">{{ $member->organisationTaxClass->name }}</dd>
                        </div>
                    @endif
                </dl>
            </x-signals.form-section>

            <x-signals.form-section title="Summary">
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Addresses</dt>
                        <dd class="mt-0.5">{{ $member->addresses_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Emails</dt>
                        <dd class="mt-0.5">{{ $member->emails_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Phones</dt>
                        <dd class="mt-0.5">{{ $member->phones_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Links</dt>
                        <dd class="mt-0.5">{{ $member->links_count }}</dd>
                    </div>
                    @if($member->organisations->isNotEmpty())
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Organisations</dt>
                            <dd class="mt-0.5">
                                @foreach($member->organisations as $org)
                                    <a href="{{ route('members.show', $org) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $org->name }}</a>@if(!$loop->last), @endif
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if($member->contacts->isNotEmpty())
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Contacts</dt>
                            <dd class="mt-0.5">
                                @foreach($member->contacts as $contact)
                                    <a href="{{ route('members.show', $contact) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $contact->name }}</a>@if(!$loop->last), @endif
                                @endforeach
                            </dd>
                        </div>
                    @endif
                </dl>
            </x-signals.form-section>
        </div>
    </div>
</section>

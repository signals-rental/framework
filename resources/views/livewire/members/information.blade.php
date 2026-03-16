<?php

use App\Actions\Members\DeleteAddress;
use App\Actions\Members\DeleteEmail;
use App\Actions\Members\DeleteLink;
use App\Actions\Members\DeletePhone;
use App\Models\CustomField;
use App\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
        $this->member->load([
            'addresses.country', 'addresses.type',
            'emails.type', 'phones.type', 'links.type',
        ]);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name . ' — Information');
    }

    public function deleteAddress(int $addressId): void
    {
        $address = $this->member->addresses()->findOrFail($addressId);
        (new DeleteAddress)($address);
        $this->member->load(['addresses.country', 'addresses.type']);
        $this->member->loadCount(['addresses']);
    }

    public function deleteEmail(int $emailId): void
    {
        $email = $this->member->emails()->findOrFail($emailId);
        (new DeleteEmail)($email);
        $this->member->load(['emails.type']);
        $this->member->loadCount(['emails']);
    }

    public function deletePhone(int $phoneId): void
    {
        $phone = $this->member->phones()->findOrFail($phoneId);
        (new DeletePhone)($phone);
        $this->member->load(['phones.type']);
        $this->member->loadCount(['phones']);
    }

    public function deleteLink(int $linkId): void
    {
        $link = $this->member->links()->findOrFail($linkId);
        (new DeleteLink)($link);
        $this->member->load(['links.type']);
        $this->member->loadCount(['links']);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $customFields = CustomField::query()
            ->forModule('Member')
            ->active()
            ->with('group')
            ->orderBy('sort_order')
            ->get();

        $customFieldValues = $this->member->customFieldValues()
            ->with('customField')
            ->get()
            ->keyBy('custom_field_id');

        $groupedFields = $customFields->groupBy(fn ($field) => $field->group?->name ?? 'General');

        return [
            'addresses' => $this->member->addresses->sortByDesc('is_primary'),
            'emails' => $this->member->emails->sortByDesc('is_primary'),
            'phones' => $this->member->phones->sortByDesc('is_primary'),
            'links' => $this->member->links->sortBy('name'),
            'groupedFields' => $groupedFields,
            'customFieldValues' => $customFieldValues,
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => 'Information'])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'information'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 space-y-6">
        {{-- Addresses --}}
        <x-signals.panel title="Addresses">
            <x-slot:headerActions>
                <a href="{{ route('members.addresses.create', $member) }}" wire:navigate class="s-btn s-btn-xs s-btn-primary">Add</a>
            </x-slot:headerActions>
            @if($addresses->isNotEmpty())
                <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
                    @foreach($addresses as $address)
                        <div wire:key="addr-{{ $address->id }}" class="s-card">
                            <div class="s-card-body">
                                <div class="flex items-center gap-2 mb-2">
                                    @if($address->type)
                                        <span class="s-badge">{{ $address->type->name }}</span>
                                    @endif
                                    @if($address->is_primary)
                                        <span class="s-badge s-badge-green">Primary</span>
                                    @endif
                                </div>
                                @if($address->name)
                                    <div class="text-sm font-semibold text-[var(--green)]" style="font-family: var(--font-display);">{{ $address->name }}</div>
                                @endif
                                <div class="text-xs text-[var(--text-secondary)] mt-1 leading-relaxed">
                                    @if($address->street){{ $address->street }}<br>@endif
                                    @if($address->city){{ $address->city }}@endif
                                    @if($address->county), {{ $address->county }}@endif
                                    @if($address->postcode) {{ $address->postcode }}@endif
                                    @if($address->country)<br>{{ $address->country->name }}@endif
                                </div>
                            </div>
                            <div class="s-card-footer" style="justify-content: flex-end;">
                                @include('livewire.members.partials.inline-actions', ['editRoute' => route('members.addresses.edit', [$member, $address]), 'deleteAction' => 'deleteAddress(' . $address->id . ')', 'deleteConfirm' => 'Are you sure you want to delete this address?'])
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-[var(--text-muted)]">No addresses yet.</p>
            @endif
        </x-signals.panel>

        {{-- Emails / Phones / Links --}}
        <div class="grid grid-cols-3 gap-4 max-md:grid-cols-1">
            <x-signals.panel title="Emails">
                <x-slot:headerActions>
                    <a href="{{ route('members.emails.create', $member) }}" wire:navigate class="s-btn s-btn-xs s-btn-primary">Add</a>
                </x-slot:headerActions>
                @forelse($emails as $email)
                    <div wire:key="email-{{ $email->id }}" class="flex items-center justify-between py-1.5 border-b border-[var(--card-border)] last:border-0">
                        <div class="min-w-0">
                            <a href="mailto:{{ $email->address }}" class="text-sm text-[var(--link)] hover:underline truncate block">{{ $email->address }}</a>
                            <div class="flex items-center gap-1 mt-1">
                                @if($email->type)<span class="s-badge s-badge-blue">{{ $email->type->name }}</span>@endif
                                @if($email->is_primary)<span class="s-badge s-badge-green">Primary</span>@endif
                            </div>
                        </div>
                        @include('livewire.members.partials.inline-actions', ['editRoute' => route('members.emails.edit', [$member, $email]), 'deleteAction' => 'deleteEmail(' . $email->id . ')', 'deleteConfirm' => 'Are you sure you want to delete this email?'])
                    </div>
                @empty
                    <p class="text-sm text-[var(--text-muted)]">No emails yet.</p>
                @endforelse
            </x-signals.panel>

            <x-signals.panel title="Phones">
                <x-slot:headerActions>
                    <a href="{{ route('members.phones.create', $member) }}" wire:navigate class="s-btn s-btn-xs s-btn-primary">Add</a>
                </x-slot:headerActions>
                @forelse($phones as $phone)
                    <div wire:key="phone-{{ $phone->id }}" class="flex items-center justify-between py-1.5 border-b border-[var(--card-border)] last:border-0">
                        <div class="min-w-0">
                            <span class="text-sm">{{ $phone->number }}</span>
                            <div class="flex items-center gap-1 mt-1">
                                @if($phone->type)<span class="s-badge s-badge-blue">{{ $phone->type->name }}</span>@endif
                                @if($phone->is_primary)<span class="s-badge s-badge-green">Primary</span>@endif
                            </div>
                        </div>
                        @include('livewire.members.partials.inline-actions', ['editRoute' => route('members.phones.edit', [$member, $phone]), 'deleteAction' => 'deletePhone(' . $phone->id . ')', 'deleteConfirm' => 'Are you sure you want to delete this phone?'])
                    </div>
                @empty
                    <p class="text-sm text-[var(--text-muted)]">No phones yet.</p>
                @endforelse
            </x-signals.panel>

            <x-signals.panel title="Links">
                <x-slot:headerActions>
                    <a href="{{ route('members.links.create', $member) }}" wire:navigate class="s-btn s-btn-xs s-btn-primary">Add</a>
                </x-slot:headerActions>
                @forelse($links as $link)
                    <div wire:key="link-{{ $link->id }}" class="flex items-center justify-between py-1.5 border-b border-[var(--card-border)] last:border-0">
                        <div class="min-w-0">
                            <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="text-sm text-[var(--link)] hover:underline truncate block">{{ Str::limit($link->url, 30) }}</a>
                            <div class="flex items-center gap-1 mt-1">
                                @if($link->name)<span class="text-xs text-[var(--text-muted)]">{{ $link->name }}</span>@endif
                                @if($link->type)<span class="s-badge s-badge-blue">{{ $link->type->name }}</span>@endif
                            </div>
                        </div>
                        @include('livewire.members.partials.inline-actions', ['editRoute' => route('members.links.edit', [$member, $link]), 'deleteAction' => 'deleteLink(' . $link->id . ')', 'deleteConfirm' => 'Are you sure you want to delete this link?'])
                    </div>
                @empty
                    <p class="text-sm text-[var(--text-muted)]">No links yet.</p>
                @endforelse
            </x-signals.panel>
        </div>

        {{-- Custom Fields --}}
        @if($groupedFields->isNotEmpty())
            <x-signals.panel title="Custom Fields">
                @foreach($groupedFields as $groupName => $fields)
                    @if(!$loop->first)
                        <div style="height: 1px; background: var(--card-border); margin: 16px 0;"></div>
                    @endif
                    <div class="text-[9px] font-medium uppercase tracking-widest text-[var(--text-muted)] mb-3" style="font-family: var(--font-mono);">{{ $groupName }}</div>
                    <div class="grid grid-cols-3 gap-x-6 gap-y-3 max-md:grid-cols-2 max-sm:grid-cols-1">
                        @foreach($fields as $field)
                            @php
                                $cfv = $customFieldValues->get($field->id);
                                $column = $field->field_type->valueColumn();
                                $value = $cfv?->{$column};
                            @endphp
                            <div wire:key="cf-{{ $field->id }}">
                                <dt class="text-[10px] font-medium uppercase tracking-wide text-[var(--text-muted)]" style="font-family: var(--font-mono);">
                                    {{ $field->display_name ?? $field->name }}
                                </dt>
                                <dd class="mt-1 text-sm">
                                    @if($field->field_type === \App\Enums\CustomFieldType::Boolean)
                                        @if($value === true)
                                            <span class="s-badge s-badge-green">Yes</span>
                                        @elseif($value === false)
                                            <span class="s-badge s-badge-zinc">No</span>
                                        @else
                                            <span class="text-[var(--text-muted)]">&mdash;</span>
                                        @endif
                                    @elseif($field->field_type === \App\Enums\CustomFieldType::Website && $value)
                                        <a href="{{ $value }}" target="_blank" rel="noopener noreferrer" class="text-[var(--link)] hover:underline">{{ Str::limit($value, 40) }}</a>
                                    @elseif($field->field_type === \App\Enums\CustomFieldType::Email && $value)
                                        <a href="mailto:{{ $value }}" class="text-[var(--link)] hover:underline">{{ $value }}</a>
                                    @elseif($field->field_type === \App\Enums\CustomFieldType::MultiListOfValues && is_array($value))
                                        @if(count($value) > 0)
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($value as $item)
                                                    <span class="s-badge s-badge-blue">{{ $item }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-[var(--text-muted)]">&mdash;</span>
                                        @endif
                                    @elseif($value !== null && $value !== '')
                                        {{ $value }}
                                    @else
                                        <span class="text-[var(--text-muted)]">&mdash;</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </x-signals.panel>
        @endif
    </div>
</section>

<x-signals.page-header :title="$member->name">
    <x-slot:breadcrumbs>
        <a href="{{ route('members.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Members</a>
        <span class="mx-1 text-[var(--text-muted)]">/</span>
        @if(isset($subpage))
            <a href="{{ route('members.show', $member) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $member->name }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $subpage }}</span>
        @else
            <span>{{ $member->name }}</span>
        @endif
    </x-slot:breadcrumbs>
    <x-slot:meta>
        @php
            $typeBadgeClass = match($member->membership_type) {
                \App\Enums\MembershipType::Organisation => 's-badge-blue',
                \App\Enums\MembershipType::Venue => 's-badge-amber',
                \App\Enums\MembershipType::Contact => 's-badge-green',
                default => 's-badge-zinc',
            };
        @endphp
        <span class="s-badge {{ $typeBadgeClass }}" style="display: inline-flex; align-items: center; gap: 4px;">
            @if($member->membership_type === \App\Enums\MembershipType::Organisation)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M14 8h1"/><path d="M14 12h1"/></svg>
            @elseif($member->membership_type === \App\Enums\MembershipType::Venue)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            @elseif($member->membership_type === \App\Enums\MembershipType::Contact)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
            @endif
            {{ $member->membership_type->label() }}
        </span>
        @if($member->is_active)
            <span class="s-badge s-badge-green"><span class="s-badge-dot"></span> Active</span>
        @else
            <span class="s-badge s-badge-zinc"><span class="s-badge-dot"></span> Inactive</span>
        @endif
    </x-slot:meta>
    <x-slot:actions>
        <a href="{{ route('members.edit', $member) }}" wire:navigate class="s-btn s-btn-sm s-btn-accent">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
            Edit
        </a>
        <x-signals.split-button label="New" size="sm">
            <a href="{{ route('members.relationships.create', $member) }}" wire:navigate class="s-dropdown-item" style="text-decoration: none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                Contact
            </a>
            <div class="s-dropdown-item" style="opacity: 0.5; cursor: default;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Activity
            </div>
            <div class="s-dropdown-item" style="opacity: 0.5; cursor: default;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                Opportunity
            </div>
            <div class="s-dropdown-item" style="opacity: 0.5; cursor: default;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M8 7h12l-2 6H6L4 3H2"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                Movement
            </div>
            <div class="s-dropdown-item" style="opacity: 0.5; cursor: default;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                Invoice
            </div>
            <div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
            <a href="{{ route('members.addresses.create', $member) }}" wire:navigate class="s-dropdown-item" style="text-decoration: none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Address
            </a>
            <a href="{{ route('members.emails.create', $member) }}" wire:navigate class="s-dropdown-item" style="text-decoration: none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                Email
            </a>
            <a href="{{ route('members.phones.create', $member) }}" wire:navigate class="s-dropdown-item" style="text-decoration: none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                Phone
            </a>
            <a href="{{ route('members.links.create', $member) }}" wire:navigate class="s-dropdown-item" style="text-decoration: none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                Link
            </a>
            <a href="{{ route('members.relationships.create', $member) }}" wire:navigate class="s-dropdown-item" style="text-decoration: none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" style="flex-shrink: 0;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Relationship
            </a>
        </x-signals.split-button>
    </x-slot:actions>
</x-signals.page-header>

{{-- Keyboard shortcuts: e = edit, n = new dropdown --}}
<div x-data x-on:keydown.window="
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
    if ($event.metaKey || $event.ctrlKey || $event.altKey) return;
    if ($event.key === 'e') { $event.preventDefault(); Livewire.navigate('{{ route('members.edit', $member) }}'); }
    if ($event.key === 'n') { $event.preventDefault(); $dispatch('open-member-new-menu'); }
" x-on:open-member-new-menu.window="document.querySelector('.s-btn-split .s-btn-split-trigger')?.click()"></div>

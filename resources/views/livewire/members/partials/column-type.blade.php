@php
    $type = $item->membership_type;
    $badgeClass = match($type) {
        \App\Enums\MembershipType::Organisation => 's-badge-blue',
        \App\Enums\MembershipType::Venue => 's-badge-amber',
        \App\Enums\MembershipType::Contact => 's-badge-green',
        default => 's-badge-zinc',
    };
@endphp
<span class="s-badge {{ $badgeClass }}" style="display: inline-flex; align-items: center; gap: 4px;">
    @if($type === \App\Enums\MembershipType::User)
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    @elseif($type === \App\Enums\MembershipType::Contact)
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
    @elseif($type === \App\Enums\MembershipType::Organisation)
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M14 8h1"/><path d="M14 12h1"/></svg>
    @elseif($type === \App\Enums\MembershipType::Venue)
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
    @endif
    {{ $type->label() }}
</span>

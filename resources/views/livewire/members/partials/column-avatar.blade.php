@php $type = $item->membership_type; @endphp
<div class="flex items-center justify-center w-8 h-8 rounded-full" style="background: var(--s-subtle);">
    @if($type === \App\Enums\MembershipType::User)
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" class="w-4 h-4"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    @elseif($type === \App\Enums\MembershipType::Contact)
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" class="w-4 h-4"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    @elseif($type === \App\Enums\MembershipType::Organisation)
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" class="w-4 h-4"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M14 8h1"/><path d="M14 12h1"/></svg>
    @elseif($type === \App\Enums\MembershipType::Venue)
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" class="w-4 h-4"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
    @endif
</div>

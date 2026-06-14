@php
    use Illuminate\Support\Facades\URL;

    $user = auth()->user();
    $isAdmin = $user->is_admin || $user->is_owner;
    $personalUrl = URL::signedRoute('calendar.feed.user', ['user' => $user->id]);
    $globalUrl = $isAdmin ? URL::signedRoute('calendar.feed.global') : null;
@endphp

<x-signals.modal name="calendar-feed" title="Calendar Feeds" size="lg">
    <div class="flex flex-col gap-4">
        <p class="text-sm" style="color: var(--text-muted);">
            Subscribe to these secure feeds from Google Calendar, Apple Calendar, or Outlook. Each URL is signed and works without signing in.
        </p>

        {{-- Current user's personal feed --}}
        <div class="flex flex-col gap-1.5">
            <span class="s-section-label">My Calendar</span>
            <div class="flex items-center gap-2">
                <input type="text" readonly value="{{ $personalUrl }}" class="s-input flex-1" style="font-family: var(--font-mono); font-size: 11px;">
                <x-signals.copy-btn :text="$personalUrl" class="shrink-0" />
            </div>
        </div>

        @if($isAdmin)
            {{-- Global feed --}}
            <div class="flex flex-col gap-1.5">
                <span class="s-section-label">All Activities (Global)</span>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $globalUrl }}" class="s-input flex-1" style="font-family: var(--font-mono); font-size: 11px;">
                    <x-signals.copy-btn :text="$globalUrl" class="shrink-0" />
                </div>
            </div>

            {{-- Per-staff feeds --}}
            <div class="flex flex-col gap-1.5">
                <span class="s-section-label">Per-Staff Feeds</span>
                <div class="flex flex-col gap-2" style="max-height: 260px; overflow-y: auto;">
                    @foreach($this->staff->reject(fn ($m) => $m->id === auth()->id()) as $member)
                        @php $staffUrl = URL::signedRoute('calendar.feed.user', ['user' => $member->id]); @endphp
                        <div class="flex items-center gap-2" wire:key="feed-staff-{{ $member->id }}">
                            <x-signals.avatar size="xs" :initials="$member->initials()" :src="app(\App\Services\FileService::class)->signedUrlOrNull($member->member?->icon_thumb_url)" :color="str_replace('s-avatar-', '', app(\App\Services\Calendar\OwnerColorResolver::class)->for($member->id))" />
                            <span class="text-sm flex-1 truncate">{{ $member->name }}</span>
                            <x-signals.copy-btn :text="$staffUrl" class="shrink-0" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-signals.modal>

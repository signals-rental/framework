<?php

use App\Actions\Activities\CompleteActivity;
use App\Actions\Activities\DeleteActivity;
use App\Data\Activities\ActivityData;
use App\Models\Activity;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $activityId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('activities.access') ?? false, 403);
    }

    /**
     * Load an activity into the detail view. Named parameter matches the calendar
     * event contract (`calendar-open-detail` dispatched with `{ activityId }`).
     */
    #[On('calendar-open-detail')]
    public function open(?int $activityId = null): void
    {
        $this->activityId = $activityId;
    }

    public function complete(): void
    {
        if ($this->activityId === null) {
            return;
        }

        abort_unless(auth()->user()?->can('activities.complete') ?? false, 403);

        $activity = Activity::findOrFail($this->activityId);
        app(CompleteActivity::class)($activity);

        $this->dispatch('calendar-refresh');
        $this->dispatch('activity-detail-done', message: 'Activity completed');
    }

    public function delete(): void
    {
        if ($this->activityId === null) {
            return;
        }

        abort_unless(auth()->user()?->can('activities.delete') ?? false, 403);

        $activity = Activity::findOrFail($this->activityId);
        app(DeleteActivity::class)($activity);

        $this->reset('activityId');

        $this->dispatch('calendar-refresh');
        $this->dispatch('activity-detail-done', message: 'Activity deleted');
    }

    /**
     * Resolve the activity for display each render (kept out of Livewire state —
     * Spatie Data objects are not serialisable as public properties).
     */
    public function getActivityProperty(): ?ActivityData
    {
        if ($this->activityId === null) {
            return null;
        }

        $activity = Activity::with(['owner', 'type', 'regarding', 'participants.member'])->find($this->activityId);

        return $activity ? ActivityData::fromModel($activity) : null;
    }

    /**
     * Participant display names, resolved to staff (User) identity where the
     * participant member is a user's linked member.
     *
     * @return list<array{name: string, initials: string, color: string, src: string|null}>
     */
    public function getParticipantNamesProperty(): array
    {
        if ($this->activityId === null) {
            return [];
        }

        $activity = Activity::with(['participants.member' => fn ($query) => $query->withTrashed()->with('user')])->find($this->activityId);

        if ($activity === null) {
            return [];
        }

        return $activity->participants
            ->map(function ($p) {
                $user = $p->member?->user;
                $name = $user?->name ?? $p->member?->name ?? 'Unknown';
                $initials = \Illuminate\Support\Str::of($name)->explode(' ')->take(2)->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->implode('');
                $color = $user !== null ? str_replace('s-avatar-', '', app(\App\Services\Calendar\OwnerColorResolver::class)->for($user->id)) : 'zinc';

                return ['name' => $name, 'initials' => $initials, 'color' => $color, 'src' => app(\App\Services\FileService::class)->signedUrlOrNull($p->member?->icon_thumb_url)];
            })
            ->values()
            ->all();
    }

    /**
     * The owner's avatar photo (signed thumbnail path) if one exists.
     */
    public function getOwnerSrcProperty(): ?string
    {
        $ownerId = $this->activity?->owner?->id;

        if ($ownerId === null) {
            return null;
        }

        return app(\App\Services\FileService::class)->signedUrlOrNull(\App\Models\User::with('member')->find($ownerId)?->member?->icon_thumb_url);
    }

    /**
     * When the activity's "regarding" is a Member, resolve avatar + link data:
     * the photo (signed thumbnail when present), initials fallback, and the
     * member record URL. Returns null for any other regarding type.
     *
     * @return array{name: string, initials: string, src: string|null, url: string}|null
     */
    public function getRegardingMemberProperty(): ?array
    {
        if ($this->activity?->regarding_type !== 'Member' || $this->activity->regarding_id === null) {
            return null;
        }

        $member = \App\Models\Member::find($this->activity->regarding_id);

        if ($member === null) {
            return null;
        }

        $name = (string) $member->name;
        $initials = \Illuminate\Support\Str::of($name)->explode(' ')->take(2)
            ->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->implode('');

        return [
            'name' => $name,
            'initials' => $initials,
            'src' => app(\App\Services\FileService::class)->signedUrlOrNull($member->icon_thumb_url),
            'url' => route('members.show', $member->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'activity' => $this->activity,
            'participantNames' => $this->participantNames,
            'ownerSrc' => $this->ownerSrc,
            'regardingMember' => $this->regardingMember,
            'canComplete' => auth()->user()?->can('activities.complete') ?? false,
            'canDelete' => auth()->user()?->can('activities.delete') ?? false,
            'canEdit' => auth()->user()?->can('activities.edit') ?? false,
        ];
    }
}; ?>

<div x-on:activity-detail-done.window="$dispatch('close-modal', 'calendar-activity-detail')">
    <x-signals.modal name="calendar-activity-detail" title="Activity" size="md">
        @if($activity)
            <div class="space-y-4">
                <div>
                    <h3 class="text-[var(--text-primary)]" style="font-family: var(--font-display); font-size: 16px; font-weight: 700;">{{ $activity->subject }}</h3>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="s-badge s-badge-blue">{{ $activity->activity_type_name }}</span>
                        <span class="s-badge {{ $activity->status_id === \App\Enums\ActivityStatus::Completed->value ? 's-badge-green' : 's-badge-amber' }}">{{ $activity->activity_status_name }}</span>
                    </div>
                </div>

                @if($activity->owner)
                    <div class="flex items-center gap-2">
                        <x-signals.avatar
                            :initials="\Illuminate\Support\Str::of($activity->owner->name)->explode(' ')->take(2)->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->implode('')"
                            :src="$ownerSrc"
                            :color="str_replace('s-avatar-', '', app(\App\Services\Calendar\OwnerColorResolver::class)->for($activity->owner->id))"
                            size="sm"
                        />
                        <span class="text-sm text-[var(--text-secondary)]">{{ $activity->owner->name }}</span>
                    </div>
                @endif

                <x-signals.data-list layout="vertical" :items="array_filter([
                    $activity->starts_at ? ['label' => 'Starts At', 'value' => app(\App\Support\Formatter::class)->dateTime($activity->starts_at)] : null,
                    $activity->ends_at ? ['label' => 'Ends At', 'value' => app(\App\Support\Formatter::class)->dateTime($activity->ends_at)] : null,
                    $activity->location ? ['label' => 'Location', 'value' => $activity->location] : null,
                ])" />

                @if($activity->description)
                    <div>
                        <div class="s-field-label">Description</div>
                        <p class="text-sm text-[var(--text-secondary)] whitespace-pre-line">{{ $activity->description }}</p>
                    </div>
                @endif

                @if($activity->regarding)
                    <div>
                        <div class="s-field-label">Regarding</div>
                        @if($regardingMember)
                            <a href="{{ $regardingMember['url'] }}" target="_blank" rel="noopener"
                               class="group flex w-fit items-center gap-2">
                                <x-signals.avatar size="sm" :initials="$regardingMember['initials']" :src="$regardingMember['src']" color="zinc" />
                                <span class="text-sm text-[var(--link)] group-hover:underline">{{ $regardingMember['name'] }}</span>
                                <svg class="h-3.5 w-3.5 text-[var(--link)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M14 5h5v5" />
                                    <path d="M19 5l-7 7" />
                                    <path d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4" />
                                </svg>
                            </a>
                        @else
                            <div class="flex items-center gap-2">
                                <span class="s-badge s-badge-zinc">{{ \App\Models\Activity::regardingTypeLabel($activity->regarding_type) }}</span>
                                <span class="text-sm text-[var(--text-secondary)]">{{ $activity->regarding->name }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                @if(count($participantNames) > 0)
                    <div>
                        <div class="s-field-label">Participants</div>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            @foreach($participantNames as $participant)
                                <div class="flex items-center gap-1.5">
                                    <x-signals.avatar size="xs" :initials="$participant['initials']" :src="$participant['src']" :color="$participant['color']" />
                                    <span class="text-sm text-[var(--text-secondary)]">{{ $participant['name'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @else
            <p class="text-sm text-[var(--text-muted)]">No activity loaded.</p>
        @endif

        <x-slot:footer>
            @if($activity)
                <a href="{{ route('activities.show', $activity->id) }}" target="_blank" rel="noopener" class="s-btn s-btn-sm s-btn-ghost">
                    Open Full Page
                </a>
                <div class="ml-auto flex items-center gap-2">
                    @if($canComplete && ! $activity->completed)
                        <button type="button" class="s-btn s-btn-sm s-btn-primary" wire:click="complete">Complete</button>
                    @endif
                    @if($canEdit)
                        <button type="button" class="s-btn s-btn-sm"
                                x-on:click="$dispatch('close-modal', 'calendar-activity-detail'); $dispatch('open-modal', 'calendar-activity-form'); $wire.dispatch('calendar-open-form', { activityId: {{ $activity->id }} })">
                            Edit
                        </button>
                    @endif
                    @if($canDelete)
                        <button type="button" class="s-btn s-btn-sm s-btn-danger" wire:click="delete" wire:confirm="Delete this activity?">Delete</button>
                    @endif
                </div>
            @endif
        </x-slot:footer>
    </x-signals.modal>
</div>

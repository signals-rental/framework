<?php

use App\Livewire\Concerns\HasFileActions;
use App\Models\Activity;
use App\Models\ActionLog;
use App\Models\CustomField;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use HasFileActions;

    /** Number of recent audit-log entries shown on the activity timeline. */
    private const TIMELINE_LIMIT = 15;

    public Activity $activity;

    public ?int $deleteAttachmentId = null;

    public function mount(Activity $activity): void
    {
        $this->activity = $activity->load([
            'owner.member',
            'type',
            'regarding',
            'participants.member' => fn ($query) => $query->withTrashed()->with('user'),
        ])->loadCount('attachments');
    }

    public function rendering(View $view): void
    {
        $view->title($this->activity->subject);
    }

    public function completeActivity(): void
    {
        (new \App\Actions\Activities\CompleteActivity)($this->activity);
        $this->activity->refresh();
    }

    public function deleteActivity(): void
    {
        (new \App\Actions\Activities\DeleteActivity)($this->activity);
        $this->redirect(route('activities.index'), navigate: true);
    }

    protected function getFileableModel(): Activity
    {
        return $this->activity;
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
        if (Activity::shortRegardingType($this->activity->regarding_type) !== 'Member' || $this->activity->regarding_id === null) {
            return null;
        }

        $member = \App\Models\Member::find($this->activity->regarding_id);

        if ($member === null) {
            return null;
        }

        $name = (string) $member->name;
        $initials = Str::of($name)->explode(' ')->take(2)
            ->map(fn ($w) => Str::substr($w, 0, 1))->implode('');

        return [
            'name' => $name,
            'initials' => $initials,
            'src' => app(\App\Services\FileService::class)->signedUrlOrNull($member->icon_thumb_url),
            'url' => route('members.show', $member->id),
        ];
    }

    /**
     * Most-recent audit-log entries for this activity, shaped for the timeline.
     *
     * @return \Illuminate\Support\Collection<int, array{title: string, meta: string, color: ?string, body: ?string}>
     */
    private function timelineEntries(): \Illuminate\Support\Collection
    {
        return ActionLog::query()
            ->with('user')
            ->forEntity($this->activity->getMorphClass(), $this->activity->id)
            ->latest('created_at')
            ->limit(self::TIMELINE_LIMIT)
            ->get()
            ->map(fn (ActionLog $log): array => [
                'title' => Str::of($log->action)->replace(['.', '_'], ' ')->headline()->toString(),
                'meta' => $log->created_at?->diffForHumans() ?? '',
                'color' => $this->timelineColor($log->action),
                'body' => $log->user?->name ? "by {$log->user->name}" : null,
            ]);
    }

    /**
     * Map an audit action to a timeline dot colour for at-a-glance scanning.
     */
    private function timelineColor(string $action): ?string
    {
        return match (true) {
            Str::endsWith($action, ['.created', '.restored', '.completed']) => 'green',
            Str::endsWith($action, ['.deleted', '.cancelled']) => 'red',
            Str::endsWith($action, ['.updated']) => 'blue',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $fields = CustomField::query()
            ->forModule('Activity')
            ->active()
            ->with('group')
            ->orderBy('sort_order')
            ->get();

        $values = $this->activity->customFieldValues()
            ->with('customField')
            ->get()
            ->keyBy('custom_field_id');

        return [
            'regardingMember' => $this->regardingMember,
            'timeline' => $this->timelineEntries(),
            'groupedCustomFields' => $fields->groupBy(fn (CustomField $f) => $f->group?->name ?? 'General'),
            'customFieldValues' => $values,
            ...$this->fileData(),
        ];
    }
}; ?>

<section class="w-full" x-data="{ tab: 'details' }">
    <x-signals.page-header :title="$activity->subject">
        <x-slot:breadcrumbs>
            <a href="{{ route('activities.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Activities</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $activity->subject }}</span>
        </x-slot:breadcrumbs>
        <x-slot:meta>
            <div class="flex items-center gap-2">
                <span class="s-badge s-badge-blue">{{ $activity->type?->name ?? '—' }}</span>
                <span class="s-badge {{ $activity->status_id === \App\Enums\ActivityStatus::Completed ? 's-badge-green' : ($activity->status_id === \App\Enums\ActivityStatus::Cancelled ? 's-badge-zinc' : 's-badge-amber') }}">
                    {{ $activity->status_id->label() }}
                </span>
                <span class="s-badge {{ $activity->priority === \App\Enums\ActivityPriority::High ? 's-badge-red' : ($activity->priority === \App\Enums\ActivityPriority::Low ? 's-badge-zinc' : 's-badge-blue') }}">
                    {{ $activity->priority->label() }} Priority
                </span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if(!$activity->completed)
                    <button wire:click="completeActivity" class="s-btn s-btn-sm s-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Complete
                    </button>
                @endif
                <a href="{{ route('activities.edit', $activity->id) }}" wire:navigate class="s-btn s-btn-sm s-btn-ghost">Edit</a>
                <button wire:click="deleteActivity" wire:confirm="Are you sure you want to delete this activity?" class="s-btn s-btn-sm s-btn-danger">Delete</button>
            </div>
        </x-slot:actions>
    </x-signals.page-header>

    {{-- Tabs --}}
    <nav class="app-subnav">
        <div class="flex h-full items-center gap-0">
            <button type="button" x-on:click="tab = 'details'" class="subnav-link" x-bind:class="{ 'active': tab === 'details' }">Details</button>
            <button type="button" x-on:click="tab = 'custom-fields'" class="subnav-link" x-bind:class="{ 'active': tab === 'custom-fields' }">Custom Fields</button>
            <button type="button" x-on:click="tab = 'files'" class="subnav-link" x-bind:class="{ 'active': tab === 'files' }">
                Files <span class="ml-1 text-[10px] text-[var(--text-muted)]">({{ $activity->attachments_count ?? 0 }})</span>
            </button>
            <button type="button" x-on:click="tab = 'timeline'" class="subnav-link" x-bind:class="{ 'active': tab === 'timeline' }">Timeline</button>
        </div>
    </nav>

    {{-- DETAILS TAB --}}
    <div x-show="tab === 'details'" class="grid grid-cols-[1fr_280px] gap-6 px-6 py-4 max-md:grid-cols-1 max-md:px-5 max-sm:px-3">
        {{-- Main Content --}}
        <div class="space-y-6">
            <x-signals.panel title="Details">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    ['label' => 'Subject', 'value' => $activity->subject],
                    $activity->description ? ['label' => 'Description', 'value' => $activity->description] : null,
                    $activity->location ? ['label' => 'Location', 'value' => $activity->location] : null,
                    ['label' => 'Type', 'value' => $activity->type?->name ?? '—'],
                    ['label' => 'Status', 'value' => $activity->status_id->label()],
                    ['label' => 'Priority', 'value' => $activity->priority->label()],
                    ['label' => 'Time Status', 'value' => $activity->time_status->label()],
                    ['label' => 'Owner', 'value' => $activity->owner?->name ?? '—'],
                    $activity->starts_at ? ['label' => 'Starts At', 'value' => $activity->starts_at->format('d M Y H:i')] : null,
                    $activity->ends_at ? ['label' => 'Ends At', 'value' => $activity->ends_at->format('d M Y H:i')] : null,
                ])" />
            </x-signals.panel>

            @if($activity->regarding)
                <x-signals.panel title="Regarding">
                    @if($regardingMember)
                        <a href="{{ $regardingMember['url'] }}" target="_blank" rel="noopener"
                           class="group flex w-fit items-center gap-2">
                            <x-signals.avatar size="sm" :initials="$regardingMember['initials']" :src="$regardingMember['src']" color="zinc" />
                            <span class="text-sm font-medium text-[var(--link)] group-hover:underline">{{ $regardingMember['name'] }}</span>
                            <svg class="h-3.5 w-3.5 text-[var(--link)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M14 5h5v5" />
                                <path d="M19 5l-7 7" />
                                <path d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4" />
                            </svg>
                        </a>
                    @else
                        <div class="flex items-center gap-2">
                            <span class="s-badge s-badge-zinc">{{ $activity->regarding_type }}</span>
                            <span class="text-sm font-medium">{{ $activity->regarding->name ?? '—' }}</span>
                        </div>
                    @endif
                </x-signals.panel>
            @endif

            {{-- Participants: owner first, then participants — resolved to the linked user's name/avatar --}}
            <x-signals.panel title="Participants">
                <div class="space-y-2">
                    @if($activity->owner)
                        @php
                            $ownerName = $activity->owner->name;
                            $ownerInitials = Str::of($ownerName)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('');
                            $ownerColor = str_replace('s-avatar-', '', app(\App\Services\Calendar\OwnerColorResolver::class)->for($activity->owner->id));
                        @endphp
                        <div class="flex items-center justify-between" wire:key="person-owner-{{ $activity->owner->id }}">
                            <div class="flex items-center gap-2">
                                <x-signals.avatar size="sm" :initials="$ownerInitials" :src="app(\App\Services\FileService::class)->signedUrlOrNull($activity->owner->member?->icon_thumb_url)" :color="$ownerColor" />
                                <span class="text-sm font-medium">{{ $ownerName }}</span>
                            </div>
                            <span class="s-badge s-badge-blue">Owner</span>
                        </div>
                    @endif
                    @foreach($activity->participants as $participant)
                        @php
                            $pUser = $participant->member?->user;
                            $pName = $pUser?->name ?? $participant->member?->name ?? 'Unknown';
                            $pInitials = Str::of($pName)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('');
                            $pColor = $pUser !== null ? str_replace('s-avatar-', '', app(\App\Services\Calendar\OwnerColorResolver::class)->for($pUser->id)) : 'zinc';
                        @endphp
                        <div class="flex items-center justify-between" wire:key="participant-{{ $participant->id }}">
                            <div class="flex items-center gap-2">
                                <x-signals.avatar size="sm" :initials="$pInitials" :src="app(\App\Services\FileService::class)->signedUrlOrNull($participant->member?->icon_thumb_url)" :color="$pColor" />
                                <span class="text-sm">{{ $pName }}</span>
                            </div>
                            @if($participant->mute)
                                <span class="s-badge s-badge-zinc">Muted</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-signals.panel>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            <x-signals.panel title="Key Info">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Created', 'value' => $activity->created_at?->format('d M Y H:i') ?? '—'],
                    ['label' => 'Updated', 'value' => $activity->updated_at?->format('d M Y H:i') ?? '—'],
                    ['label' => 'Completed', 'value' => $activity->completed ? 'Yes' : 'No'],
                ]" />
            </x-signals.panel>

            @if(!empty($activity->tag_list))
                <x-signals.panel title="Tags">
                    <div class="flex flex-wrap gap-1">
                        @foreach($activity->tag_list as $tag)
                            <span class="s-badge s-badge-blue">{{ $tag }}</span>
                        @endforeach
                    </div>
                </x-signals.panel>
            @endif
        </div>
    </div>

    {{-- CUSTOM FIELDS TAB --}}
    <div x-show="tab === 'custom-fields'" x-cloak class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="max-w-2xl space-y-8">
            <x-signals.custom-fields-display
                :grouped="$groupedCustomFields"
                :values="$customFieldValues"
                emptyMessage="No custom fields have been configured for activities."
            />
        </div>
    </div>

    {{-- FILES TAB --}}
    <div x-show="tab === 'files'" x-cloak class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[var(--text-secondary)]" style="font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em;">
                Files ({{ $totalCount }})
            </h3>
            <button
                x-data
                x-on:click="$dispatch('open-file-upload')"
                class="s-btn s-btn-sm s-btn-primary"
            >
                Upload File
            </button>
        </div>

        @include('livewire.partials.file-browser', ['entityLabel' => 'activity'])
    </div>

    {{-- TIMELINE TAB --}}
    <div x-show="tab === 'timeline'" x-cloak class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="max-w-2xl">
            <x-signals.panel title="Activity Timeline">
                @if($timeline->isEmpty())
                    <div class="text-sm text-[var(--text-muted)] py-4">No recorded history for this activity yet.</div>
                @else
                    <x-signals.timeline>
                        @foreach($timeline as $event)
                            <x-signals.timeline-item
                                :color="$event['color']"
                                :title="$event['title']"
                                :meta="$event['meta']"
                                wire:key="timeline-{{ $loop->index }}"
                            >
                                @if($event['body'])
                                    {{ $event['body'] }}
                                @endif
                            </x-signals.timeline-item>
                        @endforeach
                    </x-signals.timeline>
                @endif
            </x-signals.panel>
        </div>
    </div>

    {{-- Upload Modal (separate Livewire component for file upload isolation) --}}
    <livewire:components.file-upload-modal :model-type="\App\Models\Activity::class" :model-id="$activity->id" />
</section>

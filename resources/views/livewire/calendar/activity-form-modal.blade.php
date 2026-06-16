<?php

use App\Actions\Activities\CreateActivity;
use App\Actions\Activities\UpdateActivity;
use App\Data\Activities\CreateActivityData;
use App\Data\Activities\UpdateActivityData;
use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\TimeStatus;
use App\Models\Activity;
use App\Models\ListValue;
use App\Models\User;
use App\Services\Activities\ActivityTypeList;
use App\Services\Calendar\OwnerColorResolver;
use App\Support\Timezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $activityId = null;

    public string $subject = '';

    public ?string $description = null;

    public ?string $location = null;

    public ?int $typeId = null;

    public int $statusId = 2001;

    public int $priority = 1;

    public int $timeStatus = 0;

    public ?int $ownedBy = null;

    public ?string $regardingType = null;

    public ?int $regardingId = null;

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    /** @var list<int> */
    public array $participantIds = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('activities.access') ?? false, 403);
    }

    /**
     * Open the modal in create or edit mode. Named parameters match the calendar
     * event contract (dispatched as object keys / PHP named args):
     *   - activityId            → edit that activity
     *   - owned_by, starts_at   → prefill a new activity from a day/week slot click
     *   - date                  → prefill a new activity from a month-day click
     *                             (defaults to the working-hours window)
     */
    #[On('calendar-open-form')]
    public function open(?int $activityId = null, ?int $owned_by = null, ?string $starts_at = null, ?string $date = null): void
    {
        $this->resetForm();

        if ($activityId !== null) {
            $this->loadActivity($activityId);

            return;
        }

        // Create mode — prefill owner and start/end from the slot or month-day payload.
        $this->ownedBy = $owned_by ?? auth()->id();

        if ($starts_at !== null) {
            $this->startsAt = $starts_at;
            $this->endsAt = Carbon::parse($starts_at)->addHour()->format('Y-m-d H:i');
        } elseif ($date !== null) {
            $start = substr((string) settings('scheduling.default_start_time'), 0, 5);
            $end = substr((string) settings('scheduling.default_end_time'), 0, 5);
            $this->startsAt = $date.' '.$start;
            $this->endsAt = $date.' '.$end;
        }
    }

    public function save(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
        ]);

        abort_unless(
            auth()->user()?->can($this->activityId !== null ? 'activities.edit' : 'activities.create') ?? false,
            403,
        );

        // The owner is never also a participant.
        $ownerMemberId = $this->ownerMemberId();
        $participantIds = array_values(array_filter(
            $this->participantIds,
            fn (int $id): bool => $id !== $ownerMemberId,
        ));

        $payload = [
            'subject' => $this->subject,
            'description' => $this->description ?: null,
            'location' => $this->location ?: null,
            'type_id' => $this->typeId,
            'status_id' => $this->statusId,
            'completed' => $this->statusId === ActivityStatus::Completed->value,
            'priority' => $this->priority,
            'time_status' => $this->timeStatus,
            'owned_by' => $this->ownedBy,
            'regarding_type' => $this->regardingType,
            'regarding_id' => $this->regardingId,
            'starts_at' => $this->toUtc($this->startsAt),
            'ends_at' => $this->toUtc($this->endsAt),
            'participants' => array_map(fn (int $id): array => ['member_id' => $id], $participantIds),
        ];

        if ($this->activityId !== null) {
            $activity = Activity::findOrFail($this->activityId);
            app(UpdateActivity::class)($activity, UpdateActivityData::from($payload));
        } else {
            app(CreateActivity::class)(CreateActivityData::from($payload));
        }

        $this->dispatch('calendar-refresh');
        $this->dispatch('activity-saved', message: $this->activityId !== null ? 'Activity updated' : 'Activity created');
    }

    public function toggleParticipant(int $memberId): void
    {
        if (in_array($memberId, $this->participantIds, true)) {
            $this->participantIds = array_values(array_filter($this->participantIds, fn (int $id): bool => $id !== $memberId));
        } else {
            $this->participantIds = [...$this->participantIds, $memberId];
        }
    }

    /**
     * The owner can never also be a participant — drop them if the owner changes.
     */
    public function updatedOwnedBy(): void
    {
        $ownerMemberId = $this->ownerMemberId();

        if ($ownerMemberId !== null) {
            $this->participantIds = array_values(array_filter($this->participantIds, fn (int $id): bool => $id !== $ownerMemberId));
        }
    }

    /**
     * The linked member id of the currently-selected owner (or null).
     */
    private function ownerMemberId(): ?int
    {
        $memberId = $this->staff->firstWhere('id', $this->ownedBy)?->member_id;

        return $memberId !== null ? (int) $memberId : null;
    }

    /**
     * Convert a local 'Y-m-d H:i' form value (company timezone) to a UTC string for storage.
     */
    private function toUtc(?string $local): ?string
    {
        if ($local === null || $local === '') {
            return null;
        }

        return app(Timezone::class)->parseUserInput($local)->format('Y-m-d H:i');
    }

    /**
     * Reset every form property to its create-mode default.
     */
    private function resetForm(): void
    {
        $this->reset([
            'activityId',
            'subject',
            'description',
            'location',
            'regardingType',
            'regardingId',
            'startsAt',
            'endsAt',
            'participantIds',
        ]);

        $this->typeId = $this->defaultTypeId();
        $this->statusId = ActivityStatus::Scheduled->value;
        $this->priority = ActivityPriority::Normal->value;
        $this->timeStatus = TimeStatus::Free->value;
        $this->ownedBy = auth()->id();

        $this->resetValidation();
    }

    /**
     * Hydrate the form from an existing activity for edit mode.
     */
    private function loadActivity(int $activityId): void
    {
        $activity = Activity::with('participants')->findOrFail($activityId);

        $this->activityId = $activity->id;
        $this->subject = $activity->subject;
        $this->description = $activity->description;
        $this->location = $activity->location;
        $this->typeId = $activity->type_id;
        $this->statusId = $activity->status_id->value;
        $this->priority = $activity->priority->value;
        $this->timeStatus = $activity->time_status->value;
        $this->ownedBy = $activity->owned_by;
        $this->regardingType = Activity::shortRegardingType($activity->regarding_type);
        $this->regardingId = $activity->regarding_id;
        $timezone = app(Timezone::class);
        $this->startsAt = $activity->starts_at !== null ? $timezone->toLocal($activity->starts_at)->format('Y-m-d H:i') : null;
        $this->endsAt = $activity->ends_at !== null ? $timezone->toLocal($activity->ends_at)->format('Y-m-d H:i') : null;
        $this->participantIds = $activity->participants->pluck('member_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * The "Activity Type" list's active values, ordered for the type dropdown.
     *
     * @return Collection<int, ListValue>
     */
    #[Computed]
    public function activityTypes(): Collection
    {
        return app(ActivityTypeList::class)->activeValues();
    }

    /**
     * The default type (Task) value id for new activities.
     */
    private function defaultTypeId(): ?int
    {
        return app(ActivityTypeList::class)->defaultId();
    }

    /**
     * Active staff (system users) for the owner + participant pickers.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function staff(): Collection
    {
        $staff = User::query()
            ->with('member')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Logged-in user first, then the rest by name.
        $authId = auth()->id();

        return $staff->filter(fn (User $u): bool => $u->id === $authId)
            ->merge($staff->filter(fn (User $u): bool => $u->id !== $authId))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'staff' => $this->staff,
            'colorResolver' => app(OwnerColorResolver::class),
            'activityTypes' => $this->activityTypes,
            'activityStatuses' => ActivityStatus::cases(),
            'activityPriorities' => ActivityPriority::cases(),
            'timeStatuses' => TimeStatus::cases(),
        ];
    }
}; ?>

<div x-on:activity-saved.window="$dispatch('close-modal', 'calendar-activity-form')">
    <x-signals.modal name="calendar-activity-form" :title="$activityId ? 'Edit Activity' : 'New Activity'" size="lg">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="s-field-label" for="cal-form-subject">Subject<span class="s-field-label-required">*</span></label>
                <input
                    type="text"
                    id="cal-form-subject"
                    wire:model="subject"
                    class="s-input"
                    placeholder="What needs doing?"
                    autocomplete="off"
                />
                @error('subject') <div class="s-field-error">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                <div>
                    <label class="s-field-label">Starts At</label>
                    <x-signals.datetime-input
                        wire:key="cal-form-starts-{{ $startsAt }}"
                        :value="$startsAt"
                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('startsAt', $event.detail)"
                    />
                    @error('starts_at') <div class="s-field-error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="s-field-label">Ends At</label>
                    <x-signals.datetime-input
                        wire:key="cal-form-ends-{{ $endsAt }}"
                        :value="$endsAt"
                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('endsAt', $event.detail)"
                    />
                    @error('ends_at') <div class="s-field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                <div>
                    <label class="s-field-label" for="cal-form-type">Type</label>
                    <select id="cal-form-type" wire:model="typeId" class="s-select">
                        @foreach($activityTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="s-field-label" for="cal-form-status">Status</label>
                    <select id="cal-form-status" wire:model="statusId" class="s-select">
                        @foreach($activityStatuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                <div>
                    <label class="s-field-label" for="cal-form-priority">Priority</label>
                    <select id="cal-form-priority" wire:model="priority" class="s-select">
                        @foreach($activityPriorities as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="s-field-label" for="cal-form-time-status">Time Status</label>
                    <select id="cal-form-time-status" wire:model="timeStatus" class="s-select">
                        @foreach($timeStatuses as $ts)
                            <option value="{{ $ts->value }}">{{ $ts->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Owner picker (shows each user's calendar colour) --}}
            <div x-data="{ open: false }" class="relative">
                <label class="s-field-label">Owner</label>
                @php $selectedOwner = $staff->firstWhere('id', $ownedBy); @endphp
                <button type="button" class="s-input flex items-center gap-2 text-left w-full" x-on:click="open = !open">
                    @if($selectedOwner)
                        <x-signals.avatar size="xs" :initials="$selectedOwner->initials()" :src="app(\App\Services\FileService::class)->signedUrlOrNull($selectedOwner->member?->icon_thumb_url)" :color="str_replace('s-avatar-', '', $colorResolver->for($selectedOwner->id))" />
                        <span>{{ $selectedOwner->name }}</span>
                    @else
                        <span style="color: var(--text-muted);">Unassigned</span>
                    @endif
                </button>
                <div class="s-dropdown" x-show="open" x-cloak x-on:click.outside="open = false" style="max-height: 240px; overflow-y: auto; min-width: 220px;">
                    @foreach($staff as $member)
                        <div class="s-dropdown-item" wire:key="owner-opt-{{ $member->id }}"
                             x-on:click="open = false" wire:click="$set('ownedBy', {{ $member->id }})">
                            <x-signals.avatar size="xs" :initials="$member->initials()" :src="app(\App\Services\FileService::class)->signedUrlOrNull($member->member?->icon_thumb_url)" :color="str_replace('s-avatar-', '', $colorResolver->for($member->id))" />
                            <span>{{ $member->name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Participants (other staff added to the activity) --}}
            <div x-data="{ open: false }" class="relative">
                <label class="s-field-label">Participants</label>
                <button type="button" class="s-input flex items-center gap-2 text-left w-full" x-on:click="open = !open">
                    @if($participantIds === [])
                        <span style="color: var(--text-muted);">Add participants…</span>
                    @else
                        <span>{{ count($participantIds) }} selected</span>
                    @endif
                </button>
                <div class="s-dropdown" x-show="open" x-cloak x-on:click.outside="open = false" style="max-height: 240px; overflow-y: auto; min-width: 220px;">
                    @foreach($staff as $member)
                        @continue($member->member_id === null)
                        @continue($member->id === $ownedBy)
                        <label class="s-dropdown-item" wire:key="participant-opt-{{ $member->id }}">
                            <input type="checkbox" value="{{ $member->member_id }}"
                                   wire:click="toggleParticipant({{ $member->member_id }})"
                                   @checked(in_array($member->member_id, $participantIds, true))>
                            <x-signals.avatar size="xs" :initials="$member->initials()" :src="app(\App\Services\FileService::class)->signedUrlOrNull($member->member?->icon_thumb_url)" :color="str_replace('s-avatar-', '', $colorResolver->for($member->id))" />
                            <span>{{ $member->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="s-field-label" for="cal-form-location">Location</label>
                <input
                    type="text"
                    id="cal-form-location"
                    wire:model="location"
                    class="s-input"
                    placeholder="Optional"
                    autocomplete="off"
                />
            </div>

            <div>
                <label class="s-field-label" for="cal-form-description">Description</label>
                <textarea
                    id="cal-form-description"
                    wire:model="description"
                    class="s-textarea"
                    rows="3"
                    placeholder="Optional notes"
                ></textarea>
            </div>
        </form>

        <x-slot:footer>
            <button type="button" class="s-btn s-btn-sm" x-on:click="$dispatch('close-modal', 'calendar-activity-form')">
                Cancel
            </button>
            <button type="button" class="s-btn s-btn-sm s-btn-primary" wire:click="save">
                {{ $activityId ? 'Save Changes' : 'Create Activity' }}
            </button>
        </x-slot:footer>
    </x-signals.modal>
</div>

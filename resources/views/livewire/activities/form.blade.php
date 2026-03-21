<?php

use App\Actions\Activities\CreateActivity;
use App\Actions\Activities\UpdateActivity;
use App\Data\Activities\CreateActivityData;
use App\Data\Activities\UpdateActivityData;
use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\TimeStatus;
use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?int $activityId = null;
    public string $subject = '';
    public string $description = '';
    public string $location = '';
    public int $typeId = 1001;
    public int $statusId = 2001;
    public int $priority = 1;
    public int $timeStatus = 0;
    public ?int $ownedBy = null;
    public ?string $regardingType = null;
    public ?int $regardingId = null;
    public ?string $startsAt = null;
    public ?string $endsAt = null;

    public function mount(?Activity $activity = null): void
    {
        // Pre-populate from query params
        $regardingType = request()->query('regarding_type');
        if (is_string($regardingType) && in_array($regardingType, ['Member', 'Product', 'StockLevel'])) {
            $this->regardingType = $regardingType;
        }
        $regardingId = request()->query('regarding_id');
        if (is_string($regardingId) && is_numeric($regardingId)) {
            $this->regardingId = (int) $regardingId;
        }

        $this->ownedBy = auth()->id();

        if ($activity?->exists) {
            $this->activityId = $activity->id;
            $this->subject = $activity->subject;
            $this->description = $activity->description ?? '';
            $this->location = $activity->location ?? '';
            $this->typeId = $activity->type_id->value;
            $this->statusId = $activity->status_id->value;
            $this->priority = $activity->priority->value;
            $this->timeStatus = $activity->time_status->value;
            $this->ownedBy = $activity->owned_by;
            $this->regardingType = Activity::shortRegardingType($activity->regarding_type);
            $this->regardingId = $activity->regarding_id;
            $this->startsAt = $activity->starts_at?->format('Y-m-d\TH:i');
            $this->endsAt = $activity->ends_at?->format('Y-m-d\TH:i');
        }
    }

    public function save(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
        ]);

        $payload = [
            'subject' => $this->subject,
            'description' => $this->description ?: null,
            'location' => $this->location ?: null,
            'type_id' => $this->typeId,
            'status_id' => $this->statusId,
            'priority' => $this->priority,
            'time_status' => $this->timeStatus,
            'owned_by' => $this->ownedBy,
            'regarding_type' => $this->regardingType,
            'regarding_id' => $this->regardingId,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];

        if ($this->activityId) {
            $activity = Activity::findOrFail($this->activityId);
            $result = (new UpdateActivity)($activity, UpdateActivityData::from($payload));
        } else {
            $result = (new CreateActivity)(CreateActivityData::from($payload));
        }

        $this->redirect(route('activities.show', $result->id), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $isEditing = $this->activityId !== null;

        return [
            'isEditing' => $isEditing,
            'activityTypes' => ActivityType::cases(),
            'activityStatuses' => ActivityStatus::cases(),
            'activityPriorities' => ActivityPriority::cases(),
            'timeStatuses' => TimeStatus::cases(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'members' => Member::query()->where('is_active', true)->orderBy('name')->limit(100)->get(['id', 'name']),
        ];
    }
}; ?>

<section class="w-full">
    @if($isEditing)
        <x-signals.page-header title="Edit Activity">
            <x-slot:breadcrumbs>
                <a href="{{ route('activities.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Activities</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <a href="{{ route('activities.show', $activityId) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $subject }}</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Edit</span>
            </x-slot:breadcrumbs>
        </x-signals.page-header>
    @else
        <x-signals.page-header title="Create Activity">
            <x-slot:breadcrumbs>
                <a href="{{ route('activities.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Activities</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-signals.page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">
                {{-- LEFT COLUMN --}}
                <div class="space-y-6">
                    <x-signals.form-section title="Basic Info">
                        <div class="space-y-3">
                            <flux:input wire:model="subject" label="Subject" required />
                            <flux:textarea wire:model="description" label="Description" rows="3" />
                            <flux:input wire:model="location" label="Location" />
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Classification">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="typeId" label="Type">
                                    @foreach($activityTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="statusId" label="Status">
                                    @foreach($activityStatuses as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="priority" label="Priority">
                                    @foreach($activityPriorities as $p)
                                        <option value="{{ $p->value }}">{{ $p->label() }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="timeStatus" label="Time Status">
                                    @foreach($timeStatuses as $ts)
                                        <option value="{{ $ts->value }}">{{ $ts->label() }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Schedule">
                        <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                            <flux:input wire:model="startsAt" label="Starts At" type="datetime-local" />
                            <flux:input wire:model="endsAt" label="Ends At" type="datetime-local" />
                        </div>
                    </x-signals.form-section>

                    <div class="flex items-center gap-4 pt-2">
                        <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Activity' }}</flux:button>
                        <flux:button variant="ghost" href="{{ $isEditing ? route('activities.show', $activityId) : route('activities.index') }}" wire:navigate>Cancel</flux:button>
                    </div>
                </div>

                {{-- RIGHT COLUMN --}}
                <div class="space-y-6" style="position: sticky; top: 24px;">
                    <x-signals.form-section title="Assignment">
                        <div class="space-y-3">
                            <flux:select wire:model="ownedBy" label="Owner">
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Regarding">
                        <div class="space-y-3">
                            <flux:select wire:model.live="regardingType" label="Entity Type">
                                <option value="">None</option>
                                <option value="Member">Member</option>
                                <option value="Product">Product</option>
                                <option value="StockLevel">Stock Level</option>
                            </flux:select>

                            @if($regardingType === 'Member')
                                <flux:select wire:model="regardingId" label="Member">
                                    <option value="">Select a member...</option>
                                    @foreach($members as $member)
                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                    @endforeach
                                </flux:select>
                            @endif
                        </div>
                    </x-signals.form-section>
                </div>
            </div>
        </form>
    </div>
</section>

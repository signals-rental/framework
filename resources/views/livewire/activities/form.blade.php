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
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Support\Collection;
use App\Services\Api\RansackFilter;
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

    public string $regardingSearch = '';
    public string $regardingSelectedName = '';
    public string $ownerSearch = '';
    public string $ownerSelectedName = '';

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

        // Resolve selected names for autocomplete display
        $this->resolveRegardingName();
        $this->resolveOwnerName();
    }

    /**
     * Reset regarding selection when the entity type changes.
     */
    public function updatedRegardingType(): void
    {
        $this->regardingId = null;
        $this->regardingSearch = '';
        $this->regardingSelectedName = '';
    }

    /**
     * Search results for the regarding entity autocomplete.
     *
     * @return Collection<int, mixed>
     */
    public function getRegardingOptionsProperty(): Collection
    {
        if ($this->regardingSearch === '' || $this->regardingType === null) {
            return collect();
        }

        return match ($this->regardingType) {
            'Member' => Member::query()
                ->where('is_active', true)
                ->where('name', 'ilike', '%' . RansackFilter::escapeLike($this->regardingSearch) . '%')
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name']),
            'Product' => Product::query()
                ->where('is_active', true)
                ->where('name', 'ilike', '%' . RansackFilter::escapeLike($this->regardingSearch) . '%')
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name']),
            'StockLevel' => StockLevel::query()
                ->where('item_name', 'ilike', '%' . RansackFilter::escapeLike($this->regardingSearch) . '%')
                ->orderBy('item_name')
                ->limit(15)
                ->get(['id', 'item_name as name']),
            default => collect(),
        };
    }

    /**
     * Select a regarding entity from the autocomplete results.
     */
    public function selectRegarding(int $id, string $name): void
    {
        $this->regardingId = $id;
        $this->regardingSelectedName = $name;
        $this->regardingSearch = '';
    }

    /**
     * Clear the selected regarding entity.
     */
    public function clearRegarding(): void
    {
        $this->regardingId = null;
        $this->regardingSelectedName = '';
        $this->regardingSearch = '';
    }

    /**
     * Search results for the owner autocomplete.
     *
     * @return Collection<int, mixed>
     */
    public function getOwnerOptionsProperty(): Collection
    {
        if ($this->ownerSearch === '') {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where('name', 'ilike', '%' . RansackFilter::escapeLike($this->ownerSearch) . '%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    /**
     * Select an owner from the autocomplete results.
     */
    public function selectOwner(int $id, string $name): void
    {
        $this->ownedBy = $id;
        $this->ownerSelectedName = $name;
        $this->ownerSearch = '';
    }

    /**
     * Clear the selected owner.
     */
    public function clearOwner(): void
    {
        $this->ownedBy = null;
        $this->ownerSelectedName = '';
        $this->ownerSearch = '';
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
        return [
            'isEditing' => $this->activityId !== null,
            'activityTypes' => ActivityType::cases(),
            'activityStatuses' => ActivityStatus::cases(),
            'activityPriorities' => ActivityPriority::cases(),
            'timeStatuses' => TimeStatus::cases(),
        ];
    }

    /**
     * Resolve the display name for the currently selected regarding entity.
     */
    private function resolveRegardingName(): void
    {
        if ($this->regardingId === null || $this->regardingType === null) {
            return;
        }

        $this->regardingSelectedName = match ($this->regardingType) {
            'Member' => Member::query()->where('id', $this->regardingId)->value('name') ?? '',
            'Product' => Product::query()->where('id', $this->regardingId)->value('name') ?? '',
            'StockLevel' => StockLevel::query()->where('id', $this->regardingId)->value('item_name') ?? '',
            default => '',
        };
    }

    /**
     * Resolve the display name for the currently selected owner.
     */
    private function resolveOwnerName(): void
    {
        if ($this->ownedBy === null) {
            return;
        }

        $this->ownerSelectedName = User::query()->where('id', $this->ownedBy)->value('name') ?? '';
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
                            <div>
                                <label class="block text-sm font-medium mb-1">Owner</label>
                                @if($ownerSelectedName)
                                    <div class="flex items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-secondary)] px-3 py-2">
                                        <span class="flex-1 truncate">{{ $ownerSelectedName }}</span>
                                        <button type="button" wire:click="clearOwner" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] shrink-0">&times;</button>
                                    </div>
                                @else
                                    <div x-data="{ open: false }" x-on:click.away="open = false" class="relative">
                                        <flux:input
                                            wire:model.live.debounce.300ms="ownerSearch"
                                            placeholder="Search users..."
                                            x-on:focus="open = true"
                                            x-on:input="open = true"
                                            autocomplete="off"
                                        />
                                        @if($this->ownerOptions->isNotEmpty())
                                            <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg-primary)] shadow-lg max-h-60 overflow-y-auto">
                                                @foreach($this->ownerOptions as $option)
                                                    <button
                                                        type="button"
                                                        wire:key="owner-{{ $option->id }}"
                                                        wire:click="selectOwner({{ $option->id }}, '{{ str_replace("'", "\\'", $option->name) }}')"
                                                        x-on:click="open = false"
                                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--bg-secondary)] transition-colors"
                                                    >
                                                        {{ $option->name }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
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

                            @if($regardingType)
                                <div>
                                    <label class="block text-sm font-medium mb-1">
                                        {{ match($regardingType) { 'Member' => 'Member', 'Product' => 'Product', 'StockLevel' => 'Stock Level', default => 'Entity' } }}
                                    </label>
                                    @if($regardingSelectedName)
                                        <div class="flex items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-secondary)] px-3 py-2">
                                            <span class="flex-1 truncate">{{ $regardingSelectedName }}</span>
                                            <button type="button" wire:click="clearRegarding" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] shrink-0">&times;</button>
                                        </div>
                                    @else
                                        <div x-data="{ open: false }" x-on:click.away="open = false" class="relative">
                                            <flux:input
                                                wire:model.live.debounce.300ms="regardingSearch"
                                                placeholder="Search {{ strtolower(match($regardingType) { 'StockLevel' => 'stock levels', 'Member' => 'members', 'Product' => 'products', default => 'entities' }) }}..."
                                                x-on:focus="open = true"
                                                x-on:input="open = true"
                                                autocomplete="off"
                                            />
                                            @if($this->regardingOptions->isNotEmpty())
                                                <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg-primary)] shadow-lg max-h-60 overflow-y-auto">
                                                    @foreach($this->regardingOptions as $option)
                                                        <button
                                                            type="button"
                                                            wire:key="regarding-{{ $option->id }}"
                                                            wire:click="selectRegarding({{ $option->id }}, '{{ str_replace("'", "\\'", $option->name) }}')"
                                                            x-on:click="open = false"
                                                            class="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--bg-secondary)] transition-colors"
                                                        >
                                                            {{ $option->name }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </x-signals.form-section>
                </div>
            </div>
        </form>
    </div>
</section>

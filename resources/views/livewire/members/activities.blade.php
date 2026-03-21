<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name . ' — Activities');
    }

    public function completeActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new \App\Actions\Activities\CompleteActivity)($activity);
            $this->dispatch('activity-completed');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->dispatch('activity-completed');
        }
    }

    public function deleteActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new \App\Actions\Activities\DeleteActivity)($activity);
            $this->dispatch('activity-deleted');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->dispatch('activity-deleted');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $typeOptions = collect(ActivityType::cases())
            ->mapWithKeys(fn (ActivityType $t): array => [(string) $t->value => $t->label()])
            ->all();

        return [
            'columns' => [
                ['key' => 'type_id', 'label' => 'Type', 'sortable' => true, 'view' => 'livewire.activities.partials.column-type'],
                ['key' => 'subject', 'label' => 'Subject', 'sortable' => true],
                ['key' => 'owner', 'label' => 'Owner', 'view' => 'livewire.activities.partials.column-owner'],
                ['key' => 'starts_at', 'label' => 'Starts', 'sortable' => true],
                ['key' => 'priority', 'label' => 'Priority', 'sortable' => true, 'view' => 'livewire.activities.partials.column-priority'],
                ['key' => 'status_id', 'label' => 'Status', 'sortable' => true, 'view' => 'livewire.activities.partials.column-status'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                'forMember' => $this->member->id,
            ],
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => 'Activities'])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'activities'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex justify-end">
            <a href="{{ route('activities.create', ['regarding_type' => 'Member', 'regarding_id' => $member->id]) }}" wire:navigate class="s-btn s-btn-sm s-btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Activity
            </a>
        </div>

        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\Activity::class"
            :searchable="['subject']"
            :with="['owner']"
            :scopes="$scopes"
            :refresh-events="['activity-completed', 'activity-deleted']"
            default-sort="-created_at"
            empty-message="No activities for this member."
            actions-view="livewire.activities.partials.row-actions"
            :key="'member-activities-' . $member->id"
        />
    </div>
</section>

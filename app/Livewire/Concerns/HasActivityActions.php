<?php

namespace App\Livewire\Concerns;

use App\Models\Activity;

/** @phpstan-ignore trait.unused (used by Volt components in Blade files) */
trait HasActivityActions
{
    public function completeActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new \App\Actions\Activities\CompleteActivity)($activity);
            $this->dispatch('activity-completed');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            session()->flash('info', 'Activity was already removed.');
        }
    }

    public function deleteActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new \App\Actions\Activities\DeleteActivity)($activity);
            $this->dispatch('activity-deleted');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            session()->flash('info', 'Activity was already removed.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function activityColumns(): array
    {
        return [
            ['key' => 'type_id', 'label' => 'Type', 'sortable' => true, 'view' => 'livewire.activities.partials.column-type'],
            ['key' => 'subject', 'label' => 'Subject', 'sortable' => true],
            ['key' => 'owner', 'label' => 'Owner', 'view' => 'livewire.activities.partials.column-owner'],
            ['key' => 'starts_at', 'label' => 'Starts', 'sortable' => true],
            ['key' => 'priority', 'label' => 'Priority', 'sortable' => true, 'view' => 'livewire.activities.partials.column-priority'],
            ['key' => 'status_id', 'label' => 'Status', 'sortable' => true, 'view' => 'livewire.activities.partials.column-status'],
            ['key' => 'actions', 'type' => 'actions'],
        ];
    }
}

<?php

namespace App\Livewire\Concerns;

use App\Models\Activity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/** @phpstan-ignore trait.unused (used by Volt components in Blade files) */
trait HasActivityActions
{
    public function completeActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new \App\Actions\Activities\CompleteActivity)($activity);
            $this->dispatch('activity-completed');
        } catch (ModelNotFoundException) {
            session()->flash('info', 'Activity was already removed.');
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to complete this activity.');
        } catch (\Throwable $e) {
            Log::error('Failed to complete activity', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Something went wrong. Please try again.');
        }
    }

    public function deleteActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new \App\Actions\Activities\DeleteActivity)($activity);
            $this->dispatch('activity-deleted');
        } catch (ModelNotFoundException) {
            session()->flash('info', 'Activity was already removed.');
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to delete this activity.');
        } catch (\Throwable $e) {
            Log::error('Failed to delete activity', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Something went wrong. Please try again.');
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

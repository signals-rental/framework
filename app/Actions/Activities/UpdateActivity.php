<?php

namespace App\Actions\Activities;

use App\Data\Activities\ActivityData;
use App\Data\Activities\UpdateActivityData;
use App\Events\AuditableEvent;
use App\Models\Activity;
use App\Services\Api\WebhookService;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateActivity
{
    /**
     * Update an existing activity.
     *
     * Field update convention (Spatie Data Optional behaviour):
     *   - Absent key / `null` in DTO → field is not updated (retains current value)
     *   - Empty string `""` → field is cleared (set to null in database)
     */
    public function __invoke(Activity $activity, UpdateActivityData $data): ActivityData
    {
        Gate::authorize('activities.edit');

        return DB::transaction(function () use ($activity, $data): ActivityData {
            $attributes = collect($data->toArray())
                ->except(['participants', 'custom_fields'])
                ->reject(fn ($value) => $value === null)
                ->map(fn ($value) => $value === '' ? null : $value)
                ->all();

            if (isset($attributes['regarding_type'])) {
                $attributes['regarding_type'] = Activity::resolveRegardingType($attributes['regarding_type']);
            }

            $activity->update($attributes);

            if ($data->custom_fields !== null) {
                app(CustomFieldValidator::class)->validate('Activity', $data->custom_fields);
                $activity->syncCustomFields($data->custom_fields);
            }

            if ($data->participants !== null) {
                $activity->participants()->delete();
                foreach ($data->participants as $participant) {
                    $activity->participants()->create([
                        'member_id' => $participant['member_id'],
                        'mute' => $participant['mute'] ?? false,
                    ]);
                }
            }

            $activity->refresh();

            event(new AuditableEvent($activity, 'activity.updated'));

            app(WebhookService::class)->dispatch('activity.updated', [
                'activity' => ActivityData::fromModel($activity->load(['owner', 'participants.member']))->toArray(),
            ]);

            return ActivityData::fromModel($activity->load(['owner', 'participants.member']));
        });
    }
}

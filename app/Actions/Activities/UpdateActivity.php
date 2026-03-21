<?php

namespace App\Actions\Activities;

use App\Data\Activities\ActivityData;
use App\Data\Activities\UpdateActivityData;
use App\Events\AuditableEvent;
use App\Models\Activity;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class UpdateActivity
{
    public function __invoke(Activity $activity, UpdateActivityData $data): ActivityData
    {
        Gate::authorize('activities.edit');

        $attributes = array_filter($data->toArray(), fn ($v) => $v !== null);

        if (isset($attributes['regarding_type'])) {
            $attributes['regarding_type'] = Activity::resolveRegardingType($attributes['regarding_type']);
        }

        $activity->update($attributes);

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
    }
}

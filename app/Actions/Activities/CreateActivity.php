<?php

namespace App\Actions\Activities;

use App\Data\Activities\ActivityData;
use App\Data\Activities\CreateActivityData;
use App\Events\AuditableEvent;
use App\Models\Activity;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class CreateActivity
{
    public function __invoke(CreateActivityData $data): ActivityData
    {
        Gate::authorize('activities.create');

        $activity = Activity::create([
            'subject' => $data->subject,
            'description' => $data->description,
            'location' => $data->location,
            'regarding_id' => $data->regarding_id,
            'regarding_type' => Activity::resolveRegardingType($data->regarding_type),
            'owned_by' => $data->owned_by ?? auth()->id(),
            'starts_at' => $data->starts_at,
            'ends_at' => $data->ends_at,
            'priority' => $data->priority,
            'type_id' => $data->type_id,
            'status_id' => $data->status_id,
            'completed' => $data->completed,
            'time_status' => $data->time_status,
        ]);

        if ($data->participants !== null) {
            foreach ($data->participants as $participant) {
                $activity->participants()->create([
                    'member_id' => $participant['member_id'],
                    'mute' => $participant['mute'] ?? false,
                ]);
            }
        }

        event(new AuditableEvent($activity, 'activity.created'));

        app(WebhookService::class)->dispatch('activity.created', [
            'activity' => ActivityData::fromModel($activity->load(['owner', 'participants.member']))->toArray(),
        ]);

        return ActivityData::fromModel($activity->load(['owner', 'participants.member']));
    }
}

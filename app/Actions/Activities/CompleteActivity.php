<?php

namespace App\Actions\Activities;

use App\Data\Activities\ActivityData;
use App\Enums\ActivityStatus;
use App\Events\AuditableEvent;
use App\Models\Activity;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class CompleteActivity
{
    public function __invoke(Activity $activity): ActivityData
    {
        Gate::authorize('activities.complete');

        $activity->update([
            'status_id' => ActivityStatus::Completed,
            'completed' => true,
        ]);

        event(new AuditableEvent($activity, 'activity.completed'));

        $activity->load(['owner', 'participants.member']);

        app(WebhookService::class)->dispatch('activity.completed', [
            'activity' => ActivityData::fromModel($activity)->toArray(),
        ]);

        return ActivityData::fromModel($activity);
    }
}

<?php

namespace App\Actions\Activities;

use App\Events\AuditableEvent;
use App\Models\Activity;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteActivity
{
    public function __invoke(Activity $activity): void
    {
        Gate::authorize('activities.delete');

        DB::transaction(function () use ($activity): void {
            event(new AuditableEvent($activity, 'activity.deleted'));

            app(WebhookService::class)->dispatch('activity.deleted', [
                'id' => $activity->id,
            ]);

            $activity->delete();
        });
    }
}

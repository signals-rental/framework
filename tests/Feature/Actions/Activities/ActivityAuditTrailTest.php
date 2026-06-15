<?php

use App\Actions\Activities\CompleteActivity;
use App\Actions\Activities\CreateActivity;
use App\Actions\Activities\DeleteActivity;
use App\Actions\Activities\UpdateActivity;
use App\Data\Activities\CreateActivityData;
use App\Data\Activities\UpdateActivityData;
use App\Jobs\DeliverWebhook;
use App\Models\Activity;
use App\Models\User;
use Database\Seeders\ListOfValuesSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

/**
 * End-to-end audit-trail coverage for activity actions.
 *
 * Unlike the other action tests, these intentionally do NOT fake AuditableEvent,
 * so the auto-discovered LogAction listener runs and writes an action_logs row.
 * This proves the audit trail is persisted, not merely that the event fires.
 * DeliverWebhook is faked so no real webhook delivery is attempted.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ListOfValuesSeeder::class);
    Queue::fake([DeliverWebhook::class]);
    $this->actingAs(User::factory()->owner()->create());
});

it('records an action_logs row when an activity is created', function () {
    $result = (new CreateActivity)(CreateActivityData::from([
        'subject' => 'Audit task',
    ]));

    $this->assertDatabaseHas('action_logs', [
        'action' => 'activity.created',
        'auditable_type' => Activity::class,
        'auditable_id' => $result->id,
    ]);
});

it('records an action_logs row when an activity is updated', function () {
    $activity = Activity::factory()->create();

    (new UpdateActivity)($activity, UpdateActivityData::from(['subject' => 'Renamed task']));

    $this->assertDatabaseHas('action_logs', [
        'action' => 'activity.updated',
        'auditable_type' => Activity::class,
        'auditable_id' => $activity->id,
    ]);
});

it('records an action_logs row when an activity is completed', function () {
    $activity = Activity::factory()->create(['completed' => false]);

    (new CompleteActivity)($activity);

    $this->assertDatabaseHas('action_logs', [
        'action' => 'activity.completed',
        'auditable_type' => Activity::class,
        'auditable_id' => $activity->id,
    ]);
});

it('records an action_logs row when an activity is deleted', function () {
    $activity = Activity::factory()->create();

    (new DeleteActivity)($activity);

    $this->assertDatabaseHas('action_logs', [
        'action' => 'activity.deleted',
        'auditable_type' => Activity::class,
        'auditable_id' => $activity->id,
    ]);
});

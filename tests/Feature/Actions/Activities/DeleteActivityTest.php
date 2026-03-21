<?php

use App\Actions\Activities\DeleteActivity;
use App\Events\AuditableEvent;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('deletes an activity', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();

    (new DeleteActivity)($activity);

    expect(Activity::find($activity->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'activity.deleted';
    });
});

it('cascades deletion to participants', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();
    $member1 = Member::factory()->create();
    $member2 = Member::factory()->create();

    $activity->participants()->create(['member_id' => $member1->id]);
    $activity->participants()->create(['member_id' => $member2->id]);

    expect(ActivityParticipant::where('activity_id', $activity->id)->count())->toBe(2);

    (new DeleteActivity)($activity);

    expect(Activity::find($activity->id))->toBeNull();
    expect(ActivityParticipant::where('activity_id', $activity->id)->count())->toBe(0);
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();

    (new DeleteActivity)($activity);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);

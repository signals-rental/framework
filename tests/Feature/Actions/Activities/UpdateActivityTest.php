<?php

use App\Actions\Activities\UpdateActivity;
use App\Data\Activities\UpdateActivityData;
use App\Events\AuditableEvent;
use App\Models\Activity;
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

it('updates an activity subject', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();

    $dto = UpdateActivityData::from(['subject' => 'Updated']);
    $result = (new UpdateActivity)($activity, $dto);

    expect($result->subject)->toBe('Updated');

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'activity.updated';
    });
});

it('replaces participants when provided', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();
    $member1 = Member::factory()->create();
    $member2 = Member::factory()->create();

    $activity->participants()->create(['member_id' => $member1->id]);

    $dto = UpdateActivityData::from([
        'participants' => [
            ['member_id' => $member2->id, 'mute' => true],
        ],
    ]);
    $result = (new UpdateActivity)($activity, $dto);

    expect($result->participants)->toHaveCount(1);
    expect($result->participants[0]->member_id)->toBe($member2->id);
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();

    $dto = UpdateActivityData::from(['subject' => 'Nope']);

    (new UpdateActivity)($activity, $dto);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);

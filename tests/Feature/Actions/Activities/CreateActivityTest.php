<?php

use App\Actions\Activities\CreateActivity;
use App\Data\Activities\CreateActivityData;
use App\Enums\ActivityType;
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

it('creates an activity with valid data', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $dto = CreateActivityData::from([
        'subject' => 'Test Activity',
        'type_id' => ActivityType::Task->value,
    ]);

    $result = (new CreateActivity)($dto);

    expect($result->subject)->toBe('Test Activity')
        ->and($result->type_id)->toBe(ActivityType::Task->value);
    expect(Activity::count())->toBe(1);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'activity.created';
    });
});

it('creates an activity with participants', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $member = Member::factory()->create();

    $dto = CreateActivityData::from([
        'subject' => 'Meeting',
        'type_id' => ActivityType::Meeting->value,
        'participants' => [
            ['member_id' => $member->id, 'mute' => false],
        ],
    ]);

    $result = (new CreateActivity)($dto);

    expect($result->participants)->toHaveCount(1);
});

it('creates an activity with regarding', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $member = Member::factory()->create();

    $dto = CreateActivityData::from([
        'subject' => 'Follow up',
        'regarding_type' => 'Member',
        'regarding_id' => $member->id,
    ]);

    $result = (new CreateActivity)($dto);

    expect($result->regarding_type)->toBe('Member');
    expect($result->regarding_id)->toBe($member->id);
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $dto = CreateActivityData::from(['subject' => 'Test']);

    (new CreateActivity)($dto);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);

it('sets owned_by to current user when not provided', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $dto = CreateActivityData::from(['subject' => 'Auto-owned']);

    $result = (new CreateActivity)($dto);

    expect($result->owned_by)->toBe($user->id);
});

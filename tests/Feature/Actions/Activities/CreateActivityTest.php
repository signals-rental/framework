<?php

use App\Actions\Activities\CreateActivity;
use App\Data\Activities\CreateActivityData;
use App\Enums\ActivityType;
use App\Events\AuditableEvent;
use App\Models\Activity;
use App\Models\ListName;
use App\Models\ListValue;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\ListOfValuesSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ListOfValuesSeeder::class);
});

/**
 * Resolve an "Activity Type" list value id by its name.
 */
function activityTypeId(ActivityType $type): int
{
    $listId = ListName::query()->where('name', 'Activity Type')->value('id');

    return (int) ListValue::query()
        ->where('list_name_id', $listId)
        ->where('name', $type->label())
        ->value('id');
}

it('creates an activity with valid data', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $taskId = activityTypeId(ActivityType::Task);

    $dto = CreateActivityData::from([
        'subject' => 'Test Activity',
        'type_id' => $taskId,
    ]);

    $result = (new CreateActivity)($dto);

    expect($result->subject)->toBe('Test Activity')
        ->and($result->type_id)->toBe($taskId)
        ->and($result->activity_type_name)->toBe('Task');
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
        'type_id' => activityTypeId(ActivityType::Meeting),
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
})->throws(AuthorizationException::class);

it('sets owned_by to current user when not provided', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $dto = CreateActivityData::from(['subject' => 'Auto-owned']);

    $result = (new CreateActivity)($dto);

    expect($result->owned_by)->toBe($user->id);
});

it('rejects a type_id that is not an Activity Type list value', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    // 999999 is not a real list_value id, so the exists rule must reject it.
    expect(fn () => CreateActivityData::validateAndCreate([
        'subject' => 'Bad type',
        'type_id' => 999999,
    ]))->toThrow(ValidationException::class);
});

it('defaults the type to Task when none is provided', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $dto = CreateActivityData::from(['subject' => 'No type given']);

    $result = (new CreateActivity)($dto);

    expect($result->type_id)->toBe(activityTypeId(ActivityType::Task))
        ->and($result->activity_type_name)->toBe('Task');
});

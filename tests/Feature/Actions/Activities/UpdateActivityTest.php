<?php

use App\Actions\Activities\UpdateActivity;
use App\Data\Activities\UpdateActivityData;
use App\Enums\CustomFieldType;
use App\Events\AuditableEvent;
use App\Models\Activity;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
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

it('clears optional field to null when empty string is passed', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create(['description' => 'Original']);

    $dto = UpdateActivityData::from(['description' => '']);
    $result = (new UpdateActivity)($activity, $dto);

    expect($activity->refresh()->description)->toBeNull();
});

it('leaves field unchanged when null is passed via DTO', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create(['description' => 'Original']);

    $dto = UpdateActivityData::from(['subject' => 'New Subject']);
    (new UpdateActivity)($activity, $dto);

    expect($activity->refresh()->description)->toBe('Original');
});

it('resolves a RMS short regarding_type to a class name on update', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();
    $member = Member::factory()->create();

    $dto = UpdateActivityData::from([
        'regarding_type' => 'Member',
        'regarding_id' => $member->id,
    ]);
    (new UpdateActivity)($activity, $dto);

    expect($activity->refresh()->regarding_type)->toBe(Member::class)
        ->and($activity->regarding_id)->toBe($member->id);
});

it('syncs custom fields when provided', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $customField = CustomField::factory()->create([
        'name' => 'activity_ref',
        'module_type' => 'Activity',
        'field_type' => CustomFieldType::String,
    ]);

    $activity = Activity::factory()->create();

    $dto = UpdateActivityData::from([
        'subject' => 'With Custom Field',
        'custom_fields' => ['activity_ref' => 'ACT-123'],
    ]);

    (new UpdateActivity)($activity, $dto);

    $cfv = CustomFieldValue::query()
        ->where('custom_field_id', $customField->id)
        ->where('entity_type', Activity::class)
        ->where('entity_id', $activity->id)
        ->first();

    expect($cfv)->not->toBeNull()
        ->and($cfv->value_string)->toBe('ACT-123');
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();

    $dto = UpdateActivityData::from(['subject' => 'Nope']);

    (new UpdateActivity)($activity, $dto);
})->throws(AuthorizationException::class);

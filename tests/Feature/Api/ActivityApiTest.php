<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\ListName;
use App\Models\ListValue;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\ListOfValuesSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ListOfValuesSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

/**
 * Resolve an "Activity Type" list value id by its enum default name.
 */
function apiTypeId(ActivityType $type): int
{
    $listId = ListName::query()->where('name', 'Activity Type')->value('id');

    return (int) ListValue::query()
        ->where('list_name_id', $listId)
        ->where('name', $type->label())
        ->value('id');
}

describe('GET /api/v1/activities', function () {
    it('lists activities with pagination meta', function () {
        Activity::factory()->count(3)->create();
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/activities')
            ->assertOk()
            ->assertJsonStructure([
                'activities' => [
                    '*' => ['id', 'subject', 'type_id', 'status_id', 'priority', 'completed', 'activity_type_name', 'activity_status_name', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 3);
    });

    it('filters by type_id', function () {
        Activity::factory()->task()->create();
        Activity::factory()->meeting()->create();
        $meetingId = apiTypeId(ActivityType::Meeting);
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/activities?q[type_id_eq]={$meetingId}")
            ->assertOk();

        expect($response->json('activities'))->toHaveCount(1);
        expect($response->json('activities.0.activity_type_name'))->toBe('Meeting');
    });

    it('filters by status_id', function () {
        Activity::factory()->create();
        Activity::factory()->completed()->create();
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/activities?q[status_id_eq]=2002')
            ->assertOk();

        expect($response->json('activities'))->toHaveCount(1);
        expect($response->json('activities.0.completed'))->toBeTrue();
    });

    it('sorts by starts_at', function () {
        Activity::factory()->create(['subject' => 'Later', 'starts_at' => now()->addDays(2)]);
        Activity::factory()->create(['subject' => 'Earlier', 'starts_at' => now()->addDay()]);
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/activities?sort=starts_at')
            ->assertOk();

        expect($response->json('activities.0.subject'))->toBe('Earlier');
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/activities')->assertUnauthorized();
    });

    it('returns forbidden without proper ability', function () {
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/activities')
            ->assertForbidden();
    });
});

describe('GET /api/v1/activities/{id}', function () {
    it('returns a single activity', function () {
        $activity = Activity::factory()->create(['subject' => 'Test Activity']);
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/activities/{$activity->id}")
            ->assertOk()
            ->assertJsonPath('activity.subject', 'Test Activity')
            ->assertJsonPath('activity.type_id', apiTypeId(ActivityType::Task))
            ->assertJsonPath('activity.activity_type_name', 'Task');
    });

    it('includes owner when requested', function () {
        $user = User::factory()->create(['name' => 'John']);
        $activity = Activity::factory()->create(['owned_by' => $user->id]);
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/activities/{$activity->id}?include=owner")
            ->assertOk();

        expect($response->json('activity.owner.name'))->toBe('John');
    });

    it('includes regarding entity reference when requested', function () {
        $member = Member::factory()->create(['name' => 'Acme Ltd']);
        $activity = Activity::factory()->create([
            'regarding_type' => Member::class,
            'regarding_id' => $member->id,
        ]);
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/activities/{$activity->id}?include=regarding")
            ->assertOk();

        expect($response->json('activity.regarding.id'))->toBe($member->id)
            ->and($response->json('activity.regarding.name'))->toBe('Acme Ltd');
    });

    it('includes participants when requested', function () {
        $activity = Activity::factory()->create();
        $member = Member::factory()->create(['name' => 'Jane Doe']);
        $activity->participants()->create(['member_id' => $member->id]);
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/activities/{$activity->id}?include=participants.member")
            ->assertOk();

        expect($response->json('activity.participants'))->toHaveCount(1);
        expect($response->json('activity.participants.0.member_name'))->toBe('Jane Doe');
    });

    it('serialises the participant mute flag', function () {
        $activity = Activity::factory()->create();
        $muted = Member::factory()->create(['name' => 'Muted Member']);
        $vocal = Member::factory()->create(['name' => 'Vocal Member']);
        $activity->participants()->create(['member_id' => $muted->id, 'mute' => true]);
        $activity->participants()->create(['member_id' => $vocal->id, 'mute' => false]);
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/activities/{$activity->id}?include=participants.member")
            ->assertOk();

        /** @var list<array{member_name: string, mute: bool}> $participants */
        $participants = $response->json('activity.participants');
        $byName = collect($participants)->keyBy('member_name');
        expect($byName['Muted Member']['mute'])->toBeTrue()
            ->and($byName['Vocal Member']['mute'])->toBeFalse();
    });
});

describe('POST /api/v1/activities', function () {
    it('creates an activity', function () {
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $taskId = apiTypeId(ActivityType::Task);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/activities', [
                'subject' => 'New Task',
                'type_id' => $taskId,
                'status_id' => 2001,
                'priority' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('activity.subject', 'New Task')
            ->assertJsonPath('activity.type_id', $taskId)
            ->assertJsonPath('activity.activity_type_name', 'Task');
    });

    it('creates an activity with participants', function () {
        $member = Member::factory()->create();
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/activities', [
                'subject' => 'Meeting with client',
                'type_id' => apiTypeId(ActivityType::Meeting),
                'participants' => [
                    ['member_id' => $member->id, 'mute' => false],
                ],
            ])
            ->assertCreated();

        expect($response->json('activity.participants'))->toHaveCount(1);
    });

    it('creates an activity with regarding', function () {
        $member = Member::factory()->create();
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/activities', [
                'subject' => 'Follow up',
                'regarding_type' => 'Member',
                'regarding_id' => $member->id,
            ])
            ->assertCreated()
            ->assertJsonPath('activity.regarding_type', 'Member')
            ->assertJsonPath('activity.regarding_id', $member->id);
    });

    it('validates required subject', function () {
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/activities', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subject');
    });

    it('rejects a type_id that is not an Activity Type list value', function () {
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/activities', [
                'subject' => 'Bad type',
                'type_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type_id');
    });

    it('returns forbidden without write ability', function () {
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/activities', ['subject' => 'Test'])
            ->assertForbidden();
    });
});

describe('PUT /api/v1/activities/{id}', function () {
    it('updates an activity', function () {
        $activity = Activity::factory()->create();
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/activities/{$activity->id}", [
                'subject' => 'Updated Subject',
            ])
            ->assertOk()
            ->assertJsonPath('activity.subject', 'Updated Subject');
    });

    it('changes the type_id to a different valid list value', function () {
        $activity = Activity::factory()->task()->create();
        $meetingId = apiTypeId(ActivityType::Meeting);
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/activities/{$activity->id}", [
                'type_id' => $meetingId,
            ])
            ->assertOk()
            ->assertJsonPath('activity.type_id', $meetingId)
            ->assertJsonPath('activity.activity_type_name', 'Meeting');
    });

    it('rejects a type_id that is not an Activity Type list value', function () {
        $activity = Activity::factory()->create();
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/activities/{$activity->id}", [
                'type_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type_id');
    });
});

describe('DELETE /api/v1/activities/{id}', function () {
    it('deletes an activity', function () {
        $activity = Activity::factory()->create();
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/activities/{$activity->id}")
            ->assertNoContent();

        expect(Activity::find($activity->id))->toBeNull();
    });

    it('returns forbidden without write ability', function () {
        $activity = Activity::factory()->create();
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/activities/{$activity->id}")
            ->assertForbidden();
    });
});

describe('POST /api/v1/activities/{id}/complete', function () {
    it('marks an activity as completed', function () {
        $activity = Activity::factory()->create(['completed' => false]);
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/activities/{$activity->id}/complete")
            ->assertOk();

        expect($response->json('activity.completed'))->toBeTrue();
        expect($response->json('activity.status_id'))->toBe(2002);
        expect($response->json('activity.activity_status_name'))->toBe('Completed');
    });
});

describe('RMS response shape', function () {
    it('matches RMS field names and types', function () {
        $faxId = apiTypeId(ActivityType::Fax);
        $activity = Activity::factory()->create([
            'subject' => 'Enable dynamic e-tailers',
            'location' => 'Hereford',
            'priority' => 1,
            'type_id' => $faxId,
            'status_id' => 2001,
            'completed' => false,
            'time_status' => 0,
        ]);
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/activities/{$activity->id}")
            ->assertOk();

        $data = $response->json('activity');

        expect($data)->toHaveKeys([
            'id', 'subject', 'description', 'location',
            'regarding_id', 'regarding_type', 'owned_by',
            'starts_at', 'ends_at', 'priority',
            'type_id', 'type_code', 'status_id', 'completed', 'time_status',
            'custom_fields', 'participants',
            'activity_type_name', 'activity_status_name', 'time_status_name',
            'created_at', 'updated_at',
        ]);

        expect($data['type_id'])->toBe($faxId);
        expect($data['type_code'])->toBe(1003);
        expect($data['activity_type_name'])->toBe('Fax');
        expect($data['status_id'])->toBe(2001);
        expect($data['activity_status_name'])->toBe('Scheduled');
        expect($data['time_status'])->toBe(0);
        expect($data['time_status_name'])->toBe('Free');
        expect($data['completed'])->toBeFalse();
    });

    it('exposes the CRMS type_code for a built-in type alongside the editable type_id', function () {
        $taskId = apiTypeId(ActivityType::Task);
        $activity = Activity::factory()->task()->create();
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/activities/{$activity->id}")
            ->assertOk();

        // type_id is the editable list_values id; type_code is the RMS-aligned code.
        $response
            ->assertJsonPath('activity.type_id', $taskId)
            ->assertJsonPath('activity.type_code', 1001);

        expect($response->json('activity.type_id'))->not->toBe(1001);
    });

    it('returns a null type_code for a user-added custom activity type', function () {
        // A custom "Activity Type" list value with no CRMS code in its metadata.
        $listId = ListName::query()->where('name', 'Activity Type')->value('id');
        $customType = ListValue::query()->create([
            'list_name_id' => $listId,
            'name' => 'Site Visit',
            'sort_order' => 99,
            'is_system' => false,
            'is_active' => true,
            'metadata' => ['icon' => 'task'],
        ]);
        $activity = Activity::factory()->create(['type_id' => $customType->id]);
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/activities/{$activity->id}")
            ->assertOk()
            ->assertJsonPath('activity.type_id', $customType->id)
            ->assertJsonPath('activity.activity_type_name', 'Site Visit')
            ->assertJsonPath('activity.type_code', null);
    });
});

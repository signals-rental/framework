<?php

use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

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
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/activities?q[type_id_eq]=1005')
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
});

describe('GET /api/v1/activities/{id}', function () {
    it('returns a single activity', function () {
        $activity = Activity::factory()->create(['subject' => 'Test Activity']);
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/activities/{$activity->id}")
            ->assertOk()
            ->assertJsonPath('activity.subject', 'Test Activity')
            ->assertJsonPath('activity.type_id', 1001)
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
});

describe('POST /api/v1/activities', function () {
    it('creates an activity', function () {
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/activities', [
                'subject' => 'New Task',
                'type_id' => 1001,
                'status_id' => 2001,
                'priority' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('activity.subject', 'New Task')
            ->assertJsonPath('activity.type_id', 1001)
            ->assertJsonPath('activity.activity_type_name', 'Task');
    });

    it('creates an activity with participants', function () {
        $member = Member::factory()->create();
        $token = $this->owner->createToken('test', ['activities:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/activities', [
                'subject' => 'Meeting with client',
                'type_id' => 1005,
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

describe('CRMS response shape', function () {
    it('matches CRMS field names and types', function () {
        $activity = Activity::factory()->create([
            'subject' => 'Enable dynamic e-tailers',
            'location' => 'Hereford',
            'priority' => 1,
            'type_id' => 1003,
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
            'type_id', 'status_id', 'completed', 'time_status',
            'custom_fields', 'participants',
            'activity_type_name', 'activity_status_name', 'time_status_name',
            'created_at', 'updated_at',
        ]);

        expect($data['type_id'])->toBe(1003);
        expect($data['activity_type_name'])->toBe('Fax');
        expect($data['status_id'])->toBe(2001);
        expect($data['activity_status_name'])->toBe('Scheduled');
        expect($data['time_status'])->toBe(0);
        expect($data['time_status_name'])->toBe('Free');
        expect($data['completed'])->toBeFalse();
    });
});

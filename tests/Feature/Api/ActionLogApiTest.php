<?php

use App\Models\ActionLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/actions', function () {
    it('lists action log entries', function () {
        ActionLog::factory()->count(3)->forUser($this->owner)->create();
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions')
            ->assertOk()
            ->assertJsonStructure([
                'actions' => [
                    '*' => ['id', 'action', 'user_id', 'auditable_type', 'auditable_id', 'created_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonCount(3, 'actions');
    });

    it('filters by action type using action_eq', function () {
        ActionLog::factory()->forUser($this->owner)->create(['action' => 'created']);
        ActionLog::factory()->forUser($this->owner)->create(['action' => 'updated']);
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions?q[action_eq]=created')
            ->assertOk();

        $actions = $response->json('actions');
        expect($actions)->toHaveCount(1);
        expect($actions[0]['action'])->toBe('created');
    });

    it('filters by user_id', function () {
        $otherUser = User::factory()->create();
        ActionLog::factory()->forUser($this->owner)->create(['action' => 'created']);
        ActionLog::factory()->forUser($otherUser)->create(['action' => 'updated']);
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/actions?q[user_id_eq]={$this->owner->id}")
            ->assertOk();

        $actions = $response->json('actions');
        expect($actions)->toHaveCount(1);
        expect($actions[0]['user_id'])->toBe($this->owner->id);
    });

    it('filters by auditable_type', function () {
        ActionLog::factory()->forUser($this->owner)->create(['auditable_type' => 'App\\Models\\User']);
        ActionLog::factory()->forUser($this->owner)->create(['auditable_type' => 'App\\Models\\Member']);
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions?q[auditable_type_eq]=App\\Models\\User')
            ->assertOk();

        $actions = $response->json('actions');
        expect($actions)->toHaveCount(1);
        // Response maps FQCN to friendly snake_case name
        expect($actions[0]['auditable_type'])->toBe('user');
    });

    it('paginates results', function () {
        ActionLog::factory()->count(5)->forUser($this->owner)->create();
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions?per_page=2&page=1')
            ->assertOk()
            ->assertJsonCount(2, 'actions')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.page', 1);
    });

    it('includes user name in response', function () {
        ActionLog::factory()->forUser($this->owner)->create();
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions')
            ->assertOk();

        $actions = $response->json('actions');
        expect($actions[0]['user_name'])->toBe($this->owner->name);
    });

    it('returns null user_name when action log has no user', function () {
        ActionLog::factory()->create(['user_id' => null]);
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions')
            ->assertOk();

        $actions = $response->json('actions');
        expect($actions[0]['user_name'])->toBeNull();
        expect($actions[0]['user_id'])->toBeNull();
    });

    it('includes old_values and new_values in response', function () {
        ActionLog::factory()->forUser($this->owner)->withChanges(
            ['name' => 'Old Name'],
            ['name' => 'New Name'],
        )->create();
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions')
            ->assertOk();

        $actions = $response->json('actions');
        expect($actions[0]['old_values'])->toBe(['name' => 'Old Name']);
        expect($actions[0]['new_values'])->toBe(['name' => 'New Name']);
    });

    it('sorts by created_at ascending', function () {
        ActionLog::factory()->forUser($this->owner)->create([
            'action' => 'old_action',
            'created_at' => now()->subDay(),
        ]);
        ActionLog::factory()->forUser($this->owner)->create([
            'action' => 'new_action',
            'created_at' => now(),
        ]);
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions?sort=created_at')
            ->assertOk();

        $actions = $response->json('actions');
        expect($actions[0]['action'])->toBe('old_action');
        expect($actions[1]['action'])->toBe('new_action');
    });

    it('defaults to latest first ordering', function () {
        ActionLog::factory()->forUser($this->owner)->create([
            'action' => 'old_action',
            'created_at' => now()->subDay(),
        ]);
        ActionLog::factory()->forUser($this->owner)->create([
            'action' => 'new_action',
            'created_at' => now(),
        ]);
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions')
            ->assertOk();

        $actions = $response->json('actions');
        expect($actions[0]['action'])->toBe('new_action');
        expect($actions[1]['action'])->toBe('old_action');
    });

    it('requires action-log:read ability', function () {
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions')
            ->assertForbidden();
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/actions')
            ->assertUnauthorized();
    });

    it('returns empty list when no action logs exist', function () {
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions')
            ->assertOk()
            ->assertJsonCount(0, 'actions')
            ->assertJsonPath('meta.total', 0);
    });
});

describe('POST /api/v1/actions/export', function () {
    it('dispatches export job and returns 202', function () {
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        \Illuminate\Support\Facades\Storage::fake();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/actions/export')
            ->assertStatus(202)
            ->assertJsonPath('message', 'Accepted');

        // Verify the job ran (sync in test) and produced a cached export path
        $cacheKey = "action-log-export:{$this->owner->id}";
        expect(\Illuminate\Support\Facades\Cache::has($cacheKey))->toBeTrue();
    });

    it('passes filters to export job', function () {
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        \Illuminate\Support\Facades\Storage::fake();
        ActionLog::factory()->forUser($this->owner)->create(['action' => 'user.created']);
        ActionLog::factory()->forUser($this->owner)->create(['action' => 'user.deleted']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/actions/export', [
                'action' => 'user.created',
            ])
            ->assertStatus(202);

        $cacheKey = "action-log-export:{$this->owner->id}";
        $filename = \Illuminate\Support\Facades\Cache::get($cacheKey);
        $content = \Illuminate\Support\Facades\Storage::get($filename);

        expect($content)->toContain('user.created');
        expect($content)->not->toContain('user.deleted');
    });

    it('requires action-log:read ability for export', function () {
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/actions/export')
            ->assertForbidden();
    });

    it('requires authentication for export', function () {
        $this->postJson('/api/v1/actions/export')
            ->assertUnauthorized();
    });
});

describe('ActionLogData::fromModel', function () {
    it('converts auditable_type FQCN to friendly name', function () {
        $log = ActionLog::factory()->forUser($this->owner)->create([
            'auditable_type' => 'App\\Models\\Member',
            'auditable_id' => 1,
        ]);

        $data = \App\Data\Api\ActionLogData::fromModel($log);

        expect($data->auditable_type)->toBe('member');
    });

    it('maps all model fields correctly via fromModel', function () {
        $log = ActionLog::factory()->forUser($this->owner)->withChanges(
            ['name' => 'Before'],
            ['name' => 'After'],
        )->create([
            'action' => 'updated',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => 42,
            'ip_address' => '192.168.1.1',
        ]);

        $data = \App\Data\Api\ActionLogData::fromModel($log->load('user'));

        expect($data->id)->toBe($log->id)
            ->and($data->user_id)->toBe($this->owner->id)
            ->and($data->action)->toBe('updated')
            ->and($data->auditable_type)->toBe('user')
            ->and($data->auditable_id)->toBe(42)
            ->and($data->old_values)->toBe(['name' => 'Before'])
            ->and($data->new_values)->toBe(['name' => 'After'])
            ->and($data->ip_address)->toBe('192.168.1.1')
            ->and($data->created_at)->not->toBeNull()
            ->and($data->user_name)->toBe($this->owner->name);
    });

    it('returns ISO 8601 created_at timestamp in fromModel', function () {
        $log = ActionLog::factory()->forUser($this->owner)->create([
            'auditable_type' => 'App\\Models\\Store',
            'auditable_id' => 99,
        ]);

        $data = \App\Data\Api\ActionLogData::fromModel($log);

        expect($data->created_at)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    });
});

describe('CRMS response shape', function () {
    it('returns the complete action log field set', function () {
        $log = ActionLog::factory()->forUser($this->owner)->withChanges(
            ['name' => 'Old'],
            ['name' => 'New'],
        )->create([
            'action' => 'member.updated',
            'auditable_type' => 'App\\Models\\Member',
            'auditable_id' => 42,
            'ip_address' => '10.0.0.1',
        ]);
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions')
            ->assertOk()
            ->json('actions.0');

        // Core fields
        expect($data['id'])->toBe($log->id);
        expect($data['user_id'])->toBe($this->owner->id);
        expect($data['action'])->toBe('member.updated');
        expect($data['ip_address'])->toBe('10.0.0.1');

        // Auditable type mapped to friendly name
        expect($data['auditable_type'])->toBe('member');
        expect($data['auditable_id'])->toBe(42);

        // Change values
        expect($data['old_values'])->toBe(['name' => 'Old']);
        expect($data['new_values'])->toBe(['name' => 'New']);

        // User name resolved
        expect($data['user_name'])->toBe($this->owner->name);

        // ISO 8601 timestamp
        expect($data['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    });

    it('returns correct list response with wrapping and meta', function () {
        ActionLog::factory()->count(3)->forUser($this->owner)->create();
        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/actions')
            ->assertOk()
            ->assertJsonStructure([
                'actions' => [
                    '*' => [
                        'id', 'user_id', 'action', 'auditable_type', 'auditable_id',
                        'old_values', 'new_values', 'ip_address', 'user_name',
                        'created_at',
                    ],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        expect($response->json())->toHaveKeys(['actions', 'meta']);
        expect($response->json('meta.total'))->toBe(3);
    });
});

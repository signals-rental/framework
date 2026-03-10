<?php

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/webhooks', function () {
    it('lists webhooks for the authenticated user', function () {
        Webhook::factory()->count(2)->create(['user_id' => $this->owner->id]);
        Webhook::factory()->create(); // another user's webhook
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonCount(2, 'webhooks')
            ->assertJsonStructure(['meta' => ['total', 'per_page', 'page']])
            ->assertJsonPath('meta.total', 2);
    });

    it('returns webhook structure', function () {
        Webhook::factory()->create(['user_id' => $this->owner->id]);
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonStructure([
                'webhooks' => [
                    '*' => ['id', 'url', 'events', 'is_active', 'consecutive_failures', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);
    });

    it('requires webhooks:manage ability', function () {
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/webhooks')
            ->assertForbidden();
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/webhooks')
            ->assertUnauthorized();
    });
});

describe('GET /api/v1/webhooks/{id}', function () {
    it('shows a single webhook', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/webhooks/{$webhook->id}")
            ->assertOk()
            ->assertJsonPath('webhook.url', $webhook->url)
            ->assertJsonStructure(['webhook' => ['id', 'url', 'events', 'is_active']]);
    });

    it('returns 404 for nonexistent webhook', function () {
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/webhooks/99999')
            ->assertNotFound();
    });

    it('prevents non-owner from viewing another users webhook', function () {
        $otherUser = User::factory()->create();
        $otherUser->givePermissionTo('webhooks.manage');
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        $token = $otherUser->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/webhooks/{$webhook->id}")
            ->assertForbidden();
    });

    it('allows owner to view any webhook', function () {
        $otherUser = User::factory()->create();
        $webhook = Webhook::factory()->create(['user_id' => $otherUser->id]);
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/webhooks/{$webhook->id}")
            ->assertOk();
    });
});

describe('POST /api/v1/webhooks', function () {
    it('creates a webhook and returns the secret', function () {
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['user.created', 'user.updated'],
            ])
            ->assertCreated();

        $response->assertJsonPath('webhook.url', 'https://example.com/webhook');
        $response->assertJsonStructure(['webhook' => ['id', 'url', 'events', 'secret']]);

        $this->assertDatabaseHas('webhooks', [
            'user_id' => $this->owner->id,
            'url' => 'https://example.com/webhook',
        ]);
    });

    it('stores the correct events', function () {
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['user.created', 'role.deleted'],
            ])
            ->assertCreated();

        $response->assertJsonPath('webhook.events', ['user.created', 'role.deleted']);
    });

    it('validates required fields', function () {
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/webhooks', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url', 'events']);
    });

    it('validates event names against allowed list', function () {
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['invalid.event'],
            ])->assertUnprocessable();
    });

    it('validates url format', function () {
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/webhooks', [
                'url' => 'not-a-url',
                'events' => ['user.created'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    });

    it('requires at least one event', function () {
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => [],
            ])->assertUnprocessable();
    });

    it('requires webhooks:manage ability', function () {
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['user.created'],
            ])
            ->assertForbidden();
    });
});

describe('PUT /api/v1/webhooks/{id}', function () {
    it('updates a webhook url', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/webhooks/{$webhook->id}", [
                'url' => 'https://new-url.com/hook',
            ])
            ->assertOk()
            ->assertJsonPath('webhook.url', 'https://new-url.com/hook');
    });

    it('updates webhook events', function () {
        $webhook = Webhook::factory()->create([
            'user_id' => $this->owner->id,
            'events' => ['user.created'],
        ]);
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/webhooks/{$webhook->id}", [
                'events' => ['user.created', 'user.updated', 'role.created'],
            ])
            ->assertOk()
            ->assertJsonPath('webhook.events', ['user.created', 'user.updated', 'role.created']);
    });

    it('resets failure count when re-enabling', function () {
        $webhook = Webhook::factory()->disabled()->create([
            'user_id' => $this->owner->id,
            'consecutive_failures' => 10,
        ]);
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/webhooks/{$webhook->id}", [
                'is_active' => true,
            ])->assertOk();

        expect($webhook->fresh()->consecutive_failures)->toBe(0);
        expect($webhook->fresh()->disabled_at)->toBeNull();
    });

    it('can disable a webhook', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id, 'is_active' => true]);
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/webhooks/{$webhook->id}", [
                'is_active' => false,
            ])->assertOk();

        expect($webhook->fresh()->is_active)->toBeFalse();
    });

    it('prevents non-owner from updating another users webhook', function () {
        $otherUser = User::factory()->create();
        $otherUser->givePermissionTo('webhooks.manage');
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        $token = $otherUser->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/webhooks/{$webhook->id}", [
                'url' => 'https://hack.com/hook',
            ])
            ->assertForbidden();
    });

    it('requires webhooks:manage ability', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/webhooks/{$webhook->id}", [
                'url' => 'https://new-url.com/hook',
            ])
            ->assertForbidden();
    });
});

describe('DELETE /api/v1/webhooks/{id}', function () {
    it('deletes a webhook', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/webhooks/{$webhook->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    });

    it('prevents non-owner from deleting another users webhook', function () {
        $otherUser = User::factory()->create();
        $otherUser->givePermissionTo('webhooks.manage');
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        $token = $otherUser->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/webhooks/{$webhook->id}")
            ->assertForbidden();
    });

    it('returns 404 for nonexistent webhook', function () {
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/webhooks/99999')
            ->assertNotFound();
    });

    it('requires webhooks:manage ability', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/webhooks/{$webhook->id}")
            ->assertForbidden();
    });
});

describe('GET /api/v1/webhooks/{id}/logs', function () {
    it('lists delivery logs for a webhook', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        WebhookLog::factory()->count(3)->create(['webhook_id' => $webhook->id]);
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/webhooks/{$webhook->id}/logs")
            ->assertOk()
            ->assertJsonCount(3, 'logs')
            ->assertJsonStructure([
                'logs' => [['id', 'event', 'response_code', 'attempts', 'delivered_at']],
                'meta' => ['total', 'per_page', 'page'],
            ]);
    });

    it('paginates delivery logs', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        WebhookLog::factory()->count(5)->create(['webhook_id' => $webhook->id]);
        $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/webhooks/{$webhook->id}/logs?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'logs')
            ->assertJsonPath('meta.per_page', 2);
    });

    it('prevents non-owner from viewing another users webhook logs', function () {
        $otherUser = User::factory()->create();
        $otherUser->givePermissionTo('webhooks.manage');
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        $token = $otherUser->createToken('test', ['webhooks:manage'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/webhooks/{$webhook->id}/logs")
            ->assertForbidden();
    });

    it('requires webhooks:manage ability', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/webhooks/{$webhook->id}/logs")
            ->assertForbidden();
    });
});

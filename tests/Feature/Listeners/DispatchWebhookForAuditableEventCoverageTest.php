<?php

use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Listeners\DispatchWebhookForAuditableEvent;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

it('ignores an owned-model audit event whose action is not a registered webhook event', function () {
    Queue::fake();

    // Subscribe to every event so the only thing that can suppress a dispatch is
    // the action allow-list check inside the listener.
    Webhook::factory()->create([
        'user_id' => $this->actor->id,
        'events' => ['*'],
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()->create();

    // Opportunity IS an owned model, so the listener passes the model-ownership
    // gate — but 'opportunity.audit_only_action' is NOT in WebhookService::EVENTS,
    // so it must fall through the allow-list guard and dispatch nothing.
    app(DispatchWebhookForAuditableEvent::class)->handle(
        new AuditableEvent($opportunity, 'opportunity.audit_only_action'),
    );

    Queue::assertNotPushed(DeliverWebhook::class);
});

it('dispatches for an owned model when the action IS a registered webhook event (control)', function () {
    Queue::fake();

    Webhook::factory()->create([
        'user_id' => $this->actor->id,
        'events' => ['*'],
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()->create();

    app(DispatchWebhookForAuditableEvent::class)->handle(
        new AuditableEvent($opportunity, 'opportunity.status_changed'),
    );

    Queue::assertPushed(
        DeliverWebhook::class,
        fn (DeliverWebhook $job): bool => $job->event === 'opportunity.status_changed'
            && ($job->payload['id'] ?? null) === $opportunity->id,
    );
});

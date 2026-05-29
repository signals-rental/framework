<?php

use App\Actions\Activities\CreateActivity;
use App\Data\Activities\CreateActivityData;
use App\Jobs\DeliverWebhook;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Api\WebhookService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

/**
 * Guards against the class of defect where an action dispatches a webhook event
 * that is not registered in WebhookService::EVENTS — which silently makes the
 * event un-subscribable, since CreateWebhookData validates events against that
 * allow-list. (This is exactly how the Phase-2 product/activity/rate events were
 * dispatched but unreachable.)
 */
it('registers every action-dispatched webhook event in the EVENTS allow-list', function () {
    $dispatched = [];

    foreach (File::allFiles(app_path('Actions')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all("/->dispatch\(\s*'([a-z_]+\.[a-z_]+)'/", $file->getContents(), $matches);

        foreach ($matches[1] as $event) {
            $dispatched[$event] = true;
        }
    }

    $missing = array_values(array_diff(array_keys($dispatched), WebhookService::EVENTS));

    expect($missing)->toBe([]);
});

it('accepts API webhook subscriptions to Phase-2 entity events', function (string $event) {
    $token = $this->owner->createToken('test', ['webhooks:manage'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/webhooks', [
            'url' => 'https://example.test/webhooks',
            'events' => [$event],
        ])
        ->assertCreated()
        ->assertJsonPath('webhook.events', [$event]);
})->with([
    'product.created', 'product.updated', 'product.deleted',
    'product_group.created', 'product_group.updated', 'product_group.deleted',
    'activity.created', 'activity.updated', 'activity.deleted',
    'rate_definition.created', 'rate_definition.updated', 'rate_definition.deleted',
    'product_rate.created', 'product_rate.updated', 'product_rate.deleted',
]);

it('fires activity.created to a subscribed webhook when an activity is created', function () {
    Queue::fake();

    Webhook::factory()->create([
        'user_id' => $this->owner->id,
        'events' => ['activity.created'],
        'is_active' => true,
    ]);

    (new CreateActivity)(CreateActivityData::from([
        'subject' => 'Webhook anchor activity',
        'type_id' => 1001,
    ]));

    Queue::assertPushed(
        DeliverWebhook::class,
        fn (DeliverWebhook $job): bool => $job->event === 'activity.created',
    );
});

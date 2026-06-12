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

/**
 * Parse the event names from the Markdown event tables in docs/api/webhooks.md.
 *
 * Every documented event lives in a table row of the form `| `event.name` | … |`,
 * so a single regex over the backtick-wrapped first cell is sufficient.
 *
 * @return list<string>
 */
function documentedWebhookEvents(): array
{
    $markdown = File::get(base_path('docs/api/webhooks.md'));

    preg_match_all('/^\|\s*`([a-z_]+\.[a-z_]+)`\s*\|/m', $markdown, $matches);

    return array_values(array_unique($matches[1]));
}

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
    'member.merged', 'member.anonymised',
]);

/**
 * Keeps the public webhook docs and the canonical event allow-list in lock-step.
 * Drift in either direction is a defect: an event in EVENTS but missing from the
 * docs is undiscoverable to integrators, and an event documented but absent from
 * EVENTS cannot actually be subscribed to (CreateWebhookData rejects it).
 */
it('parses a non-trivial set of events from the webhook docs table', function () {
    // Guards both parity assertions below against a vacuous pass: if the markdown
    // table format drifts, documentedWebhookEvents() returns [] and array_diff makes
    // the parity checks pass silently. This anchors a sane lower bound instead.
    expect(documentedWebhookEvents())
        ->toBeArray()
        ->and(count(documentedWebhookEvents()))
        ->toBeGreaterThan(20, 'Webhook docs table parsing returned too few events — the markdown format likely drifted.');
});

it('documents every webhook event in WebhookService::EVENTS', function () {
    $documented = documentedWebhookEvents();

    $missingFromDocs = array_values(array_diff(WebhookService::EVENTS, $documented));

    expect($missingFromDocs)->toBe([]);
});

it('backs every documented webhook event with a WebhookService::EVENTS entry', function () {
    $documented = documentedWebhookEvents();

    $undefined = array_values(array_diff($documented, WebhookService::EVENTS));

    expect($undefined)->toBe([]);
});

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

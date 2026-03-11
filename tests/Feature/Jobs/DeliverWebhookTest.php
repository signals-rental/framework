<?php

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('generates correct HMAC-SHA256 signature', function () {
    $payload = '{"event":"user.created","data":{"id":1}}';
    $secret = 'test-secret';

    $signature = WebhookService::sign($payload, $secret);

    expect($signature)->toBe(hash_hmac('sha256', $payload, $secret));
});

it('dispatches webhook delivery to correct queue', function () {
    Queue::fake();

    $webhook = Webhook::factory()->create();

    DeliverWebhook::dispatch($webhook, 'user.created', ['id' => 1]);

    Queue::assertPushedOn('webhooks', DeliverWebhook::class);
});

it('delivers webhook and logs success', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $webhook = Webhook::factory()->create();

    (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();

    $log = WebhookLog::where('webhook_id', $webhook->id)->first();
    expect($log)->not->toBeNull();
    expect($log->response_code)->toBe(200);
    expect($log->delivered_at)->not->toBeNull();
    expect($webhook->fresh()->consecutive_failures)->toBe(0);
});

it('records failure and throws on server error for retry', function () {
    Http::fake(['*' => Http::response('Error', 500)]);

    $webhook = Webhook::factory()->create(['consecutive_failures' => 0]);

    expect(fn () => (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle())
        ->toThrow(\RuntimeException::class, 'Webhook delivery failed with HTTP 500');

    expect($webhook->fresh()->consecutive_failures)->toBe(1);
});

it('records failure without throwing on client error', function () {
    Http::fake(['*' => Http::response('Not Found', 404)]);

    $webhook = Webhook::factory()->create(['consecutive_failures' => 0]);

    (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();

    expect($webhook->fresh()->consecutive_failures)->toBe(1);
});

it('skips delivery for inactive webhooks', function () {
    Http::fake();

    $webhook = Webhook::factory()->disabled()->create();

    (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();

    Http::assertNothingSent();
});

it('sends correct headers including signature', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $webhook = Webhook::factory()->create(['secret' => 'my-secret']);

    (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Signals-Signature')
            && $request->hasHeader('X-Signals-Event')
            && $request->header('X-Signals-Event')[0] === 'user.created';
    });
});

it('auto-disables webhook after 18 consecutive failures', function () {
    Http::fake(['*' => Http::response('Error', 500)]);

    $webhook = Webhook::factory()->create(['consecutive_failures' => 17]);

    try {
        (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();
    } catch (\RuntimeException) {
        // Expected — 5xx errors now throw for retry
    }

    $fresh = $webhook->fresh();
    expect($fresh->is_active)->toBeFalse();
    expect($fresh->disabled_at)->not->toBeNull();
    expect($fresh->consecutive_failures)->toBe(18);
});

it('dispatches to subscribed webhooks via WebhookService', function () {
    Queue::fake();

    $activeWebhook = Webhook::factory()->create([
        'events' => ['user.created', 'user.updated'],
        'is_active' => true,
    ]);
    Webhook::factory()->disabled()->create([
        'events' => ['user.created'],
    ]);
    Webhook::factory()->create([
        'events' => ['role.created'],
        'is_active' => true,
    ]);

    app(WebhookService::class)->dispatch('user.created', ['id' => 1]);

    Queue::assertPushed(DeliverWebhook::class, 1);
    Queue::assertPushed(DeliverWebhook::class, function ($job) use ($activeWebhook) {
        return $job->webhook->id === $activeWebhook->id;
    });
});

it('returns list of available webhook events', function () {
    $service = new WebhookService;
    $events = $service->availableEvents();

    expect($events)->toContain('user.created');
    expect($events)->toContain('settings.updated');
    expect($events)->toContain('role.deleted');
});

it('failed method logs error', function () {
    $webhook = Webhook::factory()->create();
    $job = new DeliverWebhook($webhook, 'user.created', ['id' => 1]);

    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'permanently failed'));

    $job->failed(new \RuntimeException('Test failure'));
});

it('backoff returns correct exponential schedule', function () {
    $webhook = Webhook::factory()->create();
    $job = new DeliverWebhook($webhook, 'user.created', ['id' => 1]);

    $backoff = $job->backoff();

    expect($backoff)->toBe([60, 300, 1800, 7200, 21600, 43200]);
});

it('middleware returns ThrottlesExceptions', function () {
    $webhook = Webhook::factory()->create();
    $job = new DeliverWebhook($webhook, 'user.created', ['id' => 1]);

    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(\Illuminate\Queue\Middleware\ThrottlesExceptions::class);
});

it('handles JSON encode failure gracefully', function () {
    $webhook = Webhook::factory()->create();

    // Create a payload with invalid UTF-8 that will cause json_encode to fail
    $invalidPayload = ['data' => "\xB1\x31"];

    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'Failed to JSON-encode'));

    Http::fake();

    (new DeliverWebhook($webhook, 'user.created', $invalidPayload))->handle();

    // No HTTP request should be made
    Http::assertNothingSent();
});

it('records failure and updates log when HTTP request throws exception', function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'));

    $webhook = Webhook::factory()->create(['consecutive_failures' => 0]);

    expect(fn () => (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle())
        ->toThrow(\Illuminate\Http\Client\ConnectionException::class, 'Connection timed out');

    $log = WebhookLog::where('webhook_id', $webhook->id)->first();
    expect($log)->not->toBeNull();
    expect($log->response_body)->toContain('Connection timed out');
    expect($log->delivered_at)->toBeNull();
    expect($webhook->fresh()->consecutive_failures)->toBe(1);
});

it('truncates long response bodies', function () {
    $longBody = str_repeat('x', 20000);
    Http::fake(['*' => Http::response($longBody, 200)]);

    $webhook = Webhook::factory()->create();

    (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();

    $log = WebhookLog::where('webhook_id', $webhook->id)->first();
    expect(strlen($log->response_body))->toBeLessThanOrEqual(10000);
});

<?php

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

it('generates correct HMAC-SHA256 signature', function () {
    $payload = '{"test":"data"}';
    $secret = 'webhook-secret-key';

    $signature = WebhookService::sign($payload, $secret);

    expect($signature)->toBe(hash_hmac('sha256', $payload, $secret));
    expect($signature)->toHaveLength(64); // SHA256 hex output
});

it('dispatches to all active subscribed webhooks', function () {
    Queue::fake();

    Webhook::factory()->create(['events' => ['user.created'], 'is_active' => true]);
    Webhook::factory()->create(['events' => ['user.created'], 'is_active' => true]);
    Webhook::factory()->create(['events' => ['user.deleted'], 'is_active' => true]);

    app(WebhookService::class)->dispatch('user.created', ['id' => 1]);

    Queue::assertPushed(DeliverWebhook::class, 2);
});

it('does not dispatch to inactive webhooks', function () {
    Queue::fake();

    Webhook::factory()->disabled()->create(['events' => ['user.created']]);

    app(WebhookService::class)->dispatch('user.created', ['id' => 1]);

    Queue::assertNothingPushed();
});

it('logs error when query fails', function () {
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'Failed to query webhooks'));

    // Drop the table to force a query exception
    \Illuminate\Support\Facades\Schema::drop('webhooks');

    app(WebhookService::class)->dispatch('user.created', ['id' => 1]);
});

it('logs error and continues when individual dispatch fails', function () {
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'Failed to enqueue webhook delivery'));

    // Create two webhooks subscribed to the same event
    Webhook::factory()->count(2)->create(['events' => ['user.created'], 'is_active' => true]);

    // Swap the bus dispatcher so that dispatch() throws on the first call
    $callCount = 0;
    $original = app(\Illuminate\Contracts\Bus\Dispatcher::class);
    $mock = Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
    $mock->shouldReceive('dispatch')->andReturnUsing(function ($job) use (&$callCount, $original) {
        $callCount++;
        if ($callCount === 1) {
            throw new \RuntimeException('Queue connection failed');
        }

        return $original->dispatch($job);
    });
    app()->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $mock);

    app(WebhookService::class)->dispatch('user.created', ['id' => 1]);
});

it('returns available events constant', function () {
    $service = new WebhookService;

    expect($service->availableEvents())->toBe(WebhookService::EVENTS);
    expect($service->availableEvents())->toContain('user.created');
    expect($service->availableEvents())->toContain('settings.updated');
});

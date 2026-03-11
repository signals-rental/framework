<?php

use App\Models\Webhook;
use App\Models\WebhookLog;

it('belongs to a webhook', function () {
    $webhook = Webhook::factory()->create();
    $log = WebhookLog::factory()->create(['webhook_id' => $webhook->id]);

    expect($log->webhook)->toBeInstanceOf(Webhook::class);
    expect($log->webhook->id)->toBe($webhook->id);
});

it('casts payload to array', function () {
    $webhook = Webhook::factory()->create();
    $log = WebhookLog::factory()->create([
        'webhook_id' => $webhook->id,
        'payload' => ['event' => 'test', 'data' => ['id' => 1]],
    ]);

    expect($log->payload)->toBeArray();
    expect($log->payload['event'])->toBe('test');
});

it('casts delivered_at and next_retry_at to datetime', function () {
    $webhook = Webhook::factory()->create();
    $log = WebhookLog::factory()->create([
        'webhook_id' => $webhook->id,
        'delivered_at' => now(),
        'next_retry_at' => now()->addHour(),
    ]);

    expect($log->delivered_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
    expect($log->next_retry_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

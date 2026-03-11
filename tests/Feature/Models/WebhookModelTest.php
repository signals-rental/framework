<?php

use App\Models\Webhook;
use App\Models\WebhookLog;

describe('relationships', function () {
    it('has many logs', function () {
        $webhook = Webhook::factory()->create();

        WebhookLog::factory()->create([
            'webhook_id' => $webhook->id,
        ]);

        expect($webhook->logs)->toHaveCount(1);
        expect($webhook->logs->first())->toBeInstanceOf(WebhookLog::class);
    });
});

describe('subscribedTo', function () {
    it('returns true for a matching event', function () {
        $webhook = Webhook::factory()->create(['events' => ['user.created', 'user.updated']]);

        expect($webhook->subscribedTo('user.created'))->toBeTrue();
    });

    it('returns false for a non-matching event', function () {
        $webhook = Webhook::factory()->create(['events' => ['user.created']]);

        expect($webhook->subscribedTo('user.deleted'))->toBeFalse();
    });

    it('handles null events gracefully', function () {
        $webhook = Webhook::factory()->create();
        // Simulate null events by setting the attribute directly (column is NOT NULL in DB)
        $webhook->setAttribute('events', null);

        expect($webhook->subscribedTo('user.created'))->toBeFalse();
    });

    it('returns false when events is empty', function () {
        $webhook = Webhook::factory()->create(['events' => []]);

        expect($webhook->subscribedTo('user.created'))->toBeFalse();
    });
});

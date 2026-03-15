<?php

namespace App\Services\Api;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Available webhook event types.
     *
     * @var list<string>
     */
    public const EVENTS = [
        'user.created',
        'user.updated',
        'user.deactivated',
        'user.deleted',
        'member.created',
        'member.updated',
        'member.deleted',
        'settings.updated',
        'role.created',
        'role.updated',
        'role.deleted',
        'tax_rate.created',
        'tax_rate.updated',
        'tax_rate.deleted',
        'tax_rule.created',
        'tax_rule.updated',
        'tax_rule.deleted',
    ];

    /**
     * Generate HMAC-SHA256 signature for a webhook payload.
     */
    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Dispatch webhook deliveries for a given event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload): void
    {
        try {
            $webhooks = Webhook::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereJsonContains('events', $event)->orWhereJsonContains('events', '*'))
                ->get();
        } catch (\Throwable $e) {
            Log::error('WebhookService: Failed to query webhooks', [
                'event' => $event,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return;
        }

        foreach ($webhooks as $webhook) {
            try {
                DeliverWebhook::dispatch($webhook, $event, $payload);
            } catch (\Throwable $e) {
                Log::error('WebhookService: Failed to enqueue webhook delivery', [
                    'event' => $event,
                    'webhook_id' => $webhook->id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Get all available webhook event types.
     *
     * @return list<string>
     */
    public function availableEvents(): array
    {
        return self::EVENTS;
    }
}

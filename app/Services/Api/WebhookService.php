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
        'member.archived',
        'member.restored',
        'member.deleted',
        'member.merged',
        'member.anonymised',
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
        'product.created',
        'product.updated',
        'product.archived',
        'product.restored',
        'product.deleted',
        'product.merged',
        'product_group.created',
        'product_group.updated',
        'product_group.deleted',
        'stock_level.created',
        'stock_level.updated',
        'stock_level.deleted',
        'stock_transaction.created',
        'stock_transaction.deleted',
        'activity.created',
        'activity.updated',
        'activity.deleted',
        'activity.completed',
        'rate_definition.created',
        'rate_definition.updated',
        'rate_definition.deleted',
        'product_rate.created',
        'product_rate.updated',
        'product_rate.deleted',
        // Opportunity lifecycle (Phase-3 / M2–M3). Dispatched centrally from the
        // audit bridge via App\Listeners\DispatchWebhookForAuditableEvent — the
        // audit `action` string IS the webhook event name.
        'opportunity.created',
        'opportunity.updated',
        'opportunity.quoted',
        'opportunity.converted_to_order',
        'opportunity.status_changed',
        'opportunity.status_promoted',
        'opportunity.cloned',
        'opportunity.deleted',
        'opportunity.deal_price_set',
        'opportunity.deal_price_cleared',
        'opportunity.item_added',
        'opportunity.item_removed',
        'opportunity.item_quantity_changed',
        'opportunity.item_dates_changed',
        'opportunity.item_discount_set',
        'opportunity.item_optional_toggled',
        'opportunity.item_price_overridden',
        'opportunity.item_substituted',
        'opportunity.cost_added',
        'opportunity.cost_updated',
        'opportunity.cost_removed',
        'opportunity.version_created',
        'opportunity.version_activated',
        'opportunity.version_accepted',
        'opportunity.version_declined',
        'opportunity.version_sent',
        'opportunity.version_relabelled',
        'opportunity.version_superseded',
        'opportunity.version_deleted',
        'opportunity.asset_allocated',
        'opportunity.asset_deallocated',
        'opportunity.asset_substituted',
        'opportunity.asset_prepared',
        'opportunity.asset_preparation_reverted',
        'opportunity.asset_checked',
        'opportunity.asset_dispatched',
        'opportunity.asset_on_hire',
        'opportunity.asset_returned',
        'opportunity.asset_status_reverted',
        'opportunity.asset_container_set',
        'opportunity.asset_container_cleared',
        'opportunity.bulk_dispatched',
        'opportunity.bulk_returned',
        'opportunity.bulk_quantity_adjusted',
        // Availability read-model refresh (mirrors the Reverb broadcast).
        'availability.changed',
        // Shortage detection + acknowledgement + resolution + waitlist lifecycle
        // (Phase-3 / M3).
        'shortage.detected',
        'shortage.cleared',
        'shortage.acknowledged',
        'shortage.resolution.created',
        'shortage.resolution.confirmed',
        'shortage.resolution.in_progress',
        'shortage.resolution.fulfilled',
        'shortage.resolution.failed',
        'shortage.resolution.cancelled',
        'shortage.waitlist.created',
        'shortage.waitlist.matched',
        'shortage.waitlist.expired',
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

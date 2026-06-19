<?php

namespace App\Services\Shortages;

use App\Enums\AvailabilityEventType;
use App\Events\AuditableEvent;
use App\Listeners\DispatchWebhookForAuditableEvent;
use App\Listeners\LogAction;
use App\Models\AvailabilityEvent;
use App\Models\ShortageResolution;
use App\Models\ShortageWaitlistMonitor;
use App\Observers\DemandObserver;
use App\Services\Api\WebhookService;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use Thunk\Verbs\Facades\Verbs;

/**
 * Emits the shortage lifecycle events (shortage-resolution-sub-hires.md §9).
 *
 * Two sinks, mirroring the existing conventions:
 *
 *  1. The append-only `availability_events` log — the canonical home for
 *     availability/shortage events, written exactly like
 *     {@see DemandObserver} writes demand events.
 *  2. The {@see AuditableEvent} bridge — so resolution and acknowledgement
 *     actions land in the `action_logs` audit trail via the existing
 *     {@see LogAction} listener.
 *
 * Replay-safety: none of these touch the Verbs event stream. Shortages are
 * computed (never sourced), and resolution/acknowledgement records are
 * write-model rows created directly by their actions — there is no projection to
 * rebuild on `Verbs::replay()`, so the events are not replay-bridged. The
 * `shortage.detected`/`cleared` log rows are informational telemetry produced at
 * detection time, not derived from the event store.
 */
class ShortageEventRecorder
{
    public function __construct(private readonly WebhookService $webhooks) {}

    /**
     * Record `shortage.detected` for every shortage in a freshly-detected
     * collection (used by the confirmation gate and the badge endpoint).
     *
     * These rows are pure telemetry — they are NOT bridged onto the
     * {@see AuditableEvent} stream (unlike resolution/waitlist records), so the
     * matching `shortage.detected` webhook is dispatched directly here rather than
     * via {@see DispatchWebhookForAuditableEvent}. Detection is
     * computed (never Verbs-sourced) so this path is not part of the event store;
     * the {@see Verbs::unlessReplaying()} guard is belt-and-braces consistency
     * with every other webhook side effect.
     */
    public function detected(ShortageCollection $shortages): void
    {
        foreach ($shortages as $shortage) {
            $this->logAvailability(AvailabilityEventType::ShortageDetected, $shortage);

            Verbs::unlessReplaying(fn () => $this->webhooks->dispatch(
                'shortage.detected',
                $this->shortageWebhookPayload($shortage),
            ));
        }
    }

    /**
     * Record `shortage.cleared` for a single shortage that no longer exists.
     *
     * Telemetry only (see {@see self::detected()}); the `shortage.cleared`
     * webhook is dispatched directly here, replay-skipped.
     */
    public function cleared(Shortage $shortage, string $reason): void
    {
        $this->logAvailability(AvailabilityEventType::ShortageResolved, $shortage, ['reason' => $reason]);

        Verbs::unlessReplaying(fn () => $this->webhooks->dispatch(
            'shortage.cleared',
            $this->shortageWebhookPayload($shortage) + ['reason' => $reason],
        ));
    }

    /**
     * Record `shortage.resolution.created`: an availability-log row plus an
     * audit-trail entry against the resolution record.
     */
    public function resolutionCreated(ShortageResolution $resolution, Shortage $shortage): void
    {
        $this->logResolution(AvailabilityEventType::ShortageResolutionCreated, $resolution, $shortage);

        event(new AuditableEvent(
            model: $resolution,
            action: 'shortage.resolution.created',
            newValues: [
                'resolver_key' => $resolution->resolver_key,
                'resolution_type' => $resolution->resolution_type->value,
                'status' => $resolution->status->value,
                'quantity_resolved' => $resolution->quantity_resolved,
            ],
            metadata: ['opportunity_item_id' => $shortage->opportunityItemId],
        ));
    }

    /**
     * Record `shortage.resolution.confirmed`.
     */
    public function resolutionConfirmed(ShortageResolution $resolution): void
    {
        AvailabilityEvent::query()->create([
            'event_type' => AvailabilityEventType::ShortageResolutionConfirmed,
            'product_id' => (int) ($resolution->metadata['product_id'] ?? 0),
            'store_id' => (int) ($resolution->metadata['store_id'] ?? 0),
            'source_type' => 'shortage_resolution',
            'source_id' => $resolution->id,
            'payload' => $this->resolutionPayload($resolution),
        ]);

        event(new AuditableEvent(
            model: $resolution,
            action: 'shortage.resolution.confirmed',
            newValues: ['status' => $resolution->status->value],
        ));
    }

    /**
     * Record `shortage.resolution.in_progress` (§9.2): fulfilment has begun
     * (e.g. stock dispatched from a supplier / in transit).
     */
    public function resolutionInProgress(ShortageResolution $resolution): void
    {
        $this->logResolutionStatus(AvailabilityEventType::ShortageResolutionInProgress, $resolution);

        event(new AuditableEvent(
            model: $resolution,
            action: 'shortage.resolution.in_progress',
            newValues: ['status' => $resolution->status->value],
        ));
    }

    /**
     * Record `shortage.resolution.fulfilled` (§9.2): the resolution completed and
     * the stock is now available.
     */
    public function resolutionFulfilled(ShortageResolution $resolution): void
    {
        $this->logResolutionStatus(AvailabilityEventType::ShortageResolutionFulfilled, $resolution);

        event(new AuditableEvent(
            model: $resolution,
            action: 'shortage.resolution.fulfilled',
            newValues: ['status' => $resolution->status->value],
        ));
    }

    /**
     * Record `shortage.resolution.failed` (§9.2): the resolution attempt failed
     * (e.g. a supplier declined). The shortage may reappear on re-evaluation.
     */
    public function resolutionFailed(ShortageResolution $resolution, ?string $reason = null): void
    {
        $this->logResolutionStatus(
            AvailabilityEventType::ShortageResolutionFailed,
            $resolution,
            $reason !== null ? ['failure_reason' => $reason] : [],
        );

        event(new AuditableEvent(
            model: $resolution,
            action: 'shortage.resolution.failed',
            newValues: [
                'status' => $resolution->status->value,
                'failure_reason' => $reason ?? $resolution->cancellation_reason,
            ],
        ));
    }

    /**
     * Record `shortage.resolution.cancelled`.
     */
    public function resolutionCancelled(ShortageResolution $resolution): void
    {
        AvailabilityEvent::query()->create([
            'event_type' => AvailabilityEventType::ShortageResolutionCancelled,
            'product_id' => (int) ($resolution->metadata['product_id'] ?? 0),
            'store_id' => (int) ($resolution->metadata['store_id'] ?? 0),
            'source_type' => 'shortage_resolution',
            'source_id' => $resolution->id,
            'payload' => $this->resolutionPayload($resolution),
        ]);

        event(new AuditableEvent(
            model: $resolution,
            action: 'shortage.resolution.cancelled',
            newValues: [
                'status' => $resolution->status->value,
                'cancellation_reason' => $resolution->cancellation_reason,
            ],
        ));
    }

    /**
     * Record `shortage.waitlist.created` (§9.4): a monitor was placed on a
     * shortage to watch for freed-up availability.
     */
    public function waitlistCreated(ShortageWaitlistMonitor $monitor): void
    {
        $this->logWaitlist(AvailabilityEventType::WaitlistCreated, $monitor);

        event(new AuditableEvent(
            model: $monitor,
            action: 'shortage.waitlist.created',
            newValues: [
                'status' => $monitor->status->value,
                'quantity_needed' => $monitor->quantity_needed,
            ],
        ));
    }

    /**
     * Record `shortage.waitlist.matched` (§9.4): monitored stock became available.
     *
     * @param  array<string, mixed>  $availability  availability detail at match time
     */
    public function waitlistMatched(ShortageWaitlistMonitor $monitor, array $availability = []): void
    {
        $this->logWaitlist(AvailabilityEventType::WaitlistMatched, $monitor, $availability);

        event(new AuditableEvent(
            model: $monitor,
            action: 'shortage.waitlist.matched',
            newValues: ['status' => $monitor->status->value],
            metadata: $availability,
        ));
    }

    /**
     * Record `shortage.waitlist.expired` (§9.4): a monitor reached its expiry
     * without ever matching.
     */
    public function waitlistExpired(ShortageWaitlistMonitor $monitor): void
    {
        $this->logWaitlist(AvailabilityEventType::WaitlistExpired, $monitor);

        event(new AuditableEvent(
            model: $monitor,
            action: 'shortage.waitlist.expired',
            newValues: ['status' => $monitor->status->value],
        ));
    }

    /**
     * Write a `shortage.detected`/`cleared` availability-log row from a shortage
     * value object.
     *
     * @param  array<string, mixed>  $extra
     */
    private function logAvailability(AvailabilityEventType $type, Shortage $shortage, array $extra = []): void
    {
        AvailabilityEvent::query()->create([
            'event_type' => $type,
            'product_id' => $shortage->productId,
            'store_id' => $shortage->storeId,
            'source_type' => 'opportunity_item',
            'source_id' => $shortage->opportunityItemId,
            'payload' => $shortage->toSnapshot() + $extra,
        ]);
    }

    /**
     * Write a `shortage.resolution.created` availability-log row.
     */
    private function logResolution(AvailabilityEventType $type, ShortageResolution $resolution, Shortage $shortage): void
    {
        AvailabilityEvent::query()->create([
            'event_type' => $type,
            'product_id' => $shortage->productId,
            'store_id' => $shortage->storeId,
            'source_type' => 'shortage_resolution',
            'source_id' => $resolution->id,
            'payload' => $this->resolutionPayload($resolution) + [
                'opportunity_item_id' => $shortage->opportunityItemId,
            ],
        ]);
    }

    /**
     * Write a resolution-scoped availability-log row for a status transition,
     * stamping product/store off the resolution's metadata.
     *
     * @param  array<string, mixed>  $extra
     */
    private function logResolutionStatus(AvailabilityEventType $type, ShortageResolution $resolution, array $extra = []): void
    {
        AvailabilityEvent::query()->create([
            'event_type' => $type,
            'product_id' => (int) ($resolution->metadata['product_id'] ?? 0),
            'store_id' => (int) ($resolution->metadata['store_id'] ?? 0),
            'source_type' => 'shortage_resolution',
            'source_id' => $resolution->id,
            'payload' => $this->resolutionPayload($resolution) + $extra,
        ]);
    }

    /**
     * Write a waitlist-scoped availability-log row.
     *
     * @param  array<string, mixed>  $extra
     */
    private function logWaitlist(AvailabilityEventType $type, ShortageWaitlistMonitor $monitor, array $extra = []): void
    {
        AvailabilityEvent::query()->create([
            'event_type' => $type,
            'product_id' => $monitor->product_id,
            'store_id' => $monitor->store_id,
            'source_type' => 'shortage_waitlist_monitor',
            'source_id' => $monitor->id,
            'payload' => [
                'status' => $monitor->status->value,
                'quantity_needed' => $monitor->quantity_needed,
                'shortage_resolution_id' => $monitor->shortage_resolution_id,
                'starts_at' => $monitor->starts_at?->utc()->toIso8601String(),
                'ends_at' => $monitor->ends_at?->utc()->toIso8601String(),
            ] + $extra,
        ]);
    }

    /**
     * The lean outbound webhook payload for a `shortage.detected`/`cleared`
     * event — the identifying ids and the shortfall, no heavy relations.
     *
     * @return array<string, mixed>
     */
    private function shortageWebhookPayload(Shortage $shortage): array
    {
        return [
            'opportunity_id' => $shortage->opportunityId,
            'opportunity_item_id' => $shortage->opportunityItemId,
            'product_id' => $shortage->productId,
            'store_id' => $shortage->storeId,
            'shortfall' => $shortage->shortfall,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolutionPayload(ShortageResolution $resolution): array
    {
        return [
            'resolver_key' => $resolution->resolver_key,
            'resolution_type' => $resolution->resolution_type->value,
            'status' => $resolution->status->value,
            'quantity_resolved' => $resolution->quantity_resolved,
        ];
    }
}

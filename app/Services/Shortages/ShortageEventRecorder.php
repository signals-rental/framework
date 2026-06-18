<?php

namespace App\Services\Shortages;

use App\Enums\AvailabilityEventType;
use App\Events\AuditableEvent;
use App\Listeners\LogAction;
use App\Models\AvailabilityEvent;
use App\Models\ShortageResolution;
use App\Observers\DemandObserver;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;

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
    /**
     * Record `shortage.detected` for every shortage in a freshly-detected
     * collection (used by the confirmation gate and the badge endpoint).
     */
    public function detected(ShortageCollection $shortages): void
    {
        foreach ($shortages as $shortage) {
            $this->logAvailability(AvailabilityEventType::ShortageDetected, $shortage);
        }
    }

    /**
     * Record `shortage.cleared` for a single shortage that no longer exists.
     */
    public function cleared(Shortage $shortage, string $reason): void
    {
        $this->logAvailability(AvailabilityEventType::ShortageResolved, $shortage, ['reason' => $reason]);
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

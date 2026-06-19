<?php

namespace App\Listeners;

use App\Data\Opportunities\OpportunityData;
use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Opportunity;
use App\Services\Api\WebhookService;
use Thunk\Verbs\Facades\Verbs;

/**
 * Bridges the audit event stream onto the outbound webhook bus.
 *
 * Every committed opportunity Verbs mutation, plus the shortage resolution and
 * waitlist lifecycle records, already reach the Laravel bus as an
 * {@see AuditableEvent} (the M2 audit bridge). This listener rides that same
 * bridge to dispatch the matching webhook, so the whole Phase-3 mutation
 * taxonomy becomes subscribable in ONE place rather than editing dozens of
 * events. The audit `action` string IS the webhook event name — the taxonomy is
 * shared verbatim (e.g. `opportunity.converted_to_order`,
 * `shortage.resolution.created`, `shortage.waitlist.matched`).
 *
 * Replay-safety (the headline guarantee): webhooks are fire-once external side
 * effects and MUST NOT re-fire when the event store is rebuilt. The
 * opportunity-sourced AuditableEvents are dispatched from inside the firing
 * Verbs event's handle(), which Verbs re-runs in Phase::Replay; this listener
 * therefore short-circuits whenever {@see Verbs::isReplaying()} is true. The
 * audit listener ({@see LogAction}) intentionally does NOT skip replay (it
 * dedups via firstOrCreate on verb_event_id), so audit semantics are untouched —
 * only the webhook side effect is gated. Shortage/waitlist AuditableEvents carry
 * no verb_event_id and are never produced during replay, but the same guard
 * still applies harmlessly.
 *
 * Delivery itself is fire-and-forget and afterCommit: {@see WebhookService}
 * swallows query/enqueue failures and the {@see DeliverWebhook} job is
 * dispatched afterCommit, so a rolled-back transaction never delivers and a
 * webhook failure can never break the underlying operation.
 */
class DispatchWebhookForAuditableEvent
{
    /**
     * Audit actions whose webhook payload carries the full opportunity DTO under
     * the `opportunity` key (create/update-shaped events), following the Phase-2
     * entity-event convention. Every other opportunity.* action is a lifecycle /
     * line-item / asset / cost / version transition and ships the lean id-only
     * payload instead.
     *
     * @var list<string>
     */
    private const OPPORTUNITY_ENTITY_ACTIONS = [
        'opportunity.created',
        'opportunity.updated',
        'opportunity.cloned',
    ];

    public function __construct(private readonly WebhookService $webhooks) {}

    /**
     * Dispatch the webhook that mirrors this audit event, unless we are replaying
     * the Verbs event store.
     */
    public function handle(AuditableEvent $event): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        // Only audit actions whose name is a registered webhook event are
        // dispatched; anything outside the allow-list (e.g. a future audit-only
        // action) is silently ignored so audit and webhooks can diverge safely.
        if (! in_array($event->action, WebhookService::EVENTS, true)) {
            return;
        }

        $this->webhooks->dispatch($event->action, $this->payloadFor($event));
    }

    /**
     * Build the lean webhook payload for an audit event from the model it
     * carries, without triggering heavy lazy-loads.
     *
     * @return array<string, mixed>
     */
    private function payloadFor(AuditableEvent $event): array
    {
        $model = $event->model;

        if ($model instanceof Opportunity
            && in_array($event->action, self::OPPORTUNITY_ENTITY_ACTIONS, true)) {
            // The AuditableEvent carries the just-projected model, whose in-memory
            // attributes may not reflect database column defaults written during
            // the insert (e.g. exchange_rate_locked / tax_locked). Re-read a fresh
            // row so the serialised DTO is fully and correctly hydrated.
            $fresh = Opportunity::query()->find($model->getKey()) ?? $model;

            return ['opportunity' => OpportunityData::fromModel($fresh)->toArray()];
        }

        // Lifecycle / line-item / asset / cost / version transitions, and the
        // shortage resolution + waitlist events: a lean id + action envelope.
        // Consumers re-read the entity endpoint for authoritative detail.
        return [
            'id' => $model->getKey(),
            'action' => $event->action,
        ];
    }
}

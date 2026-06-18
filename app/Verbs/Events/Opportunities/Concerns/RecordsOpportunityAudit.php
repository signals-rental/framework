<?php

namespace App\Verbs\Events\Opportunities\Concerns;

use App\Events\AuditableEvent;
use App\Models\Opportunity;
use Thunk\Verbs\Event;
use Thunk\Verbs\Models\VerbEvent;

/**
 * Bridges an opportunity Verbs event's projection into the existing audit
 * pipeline (AuditableEvent → LogAction → action_logs).
 *
 * Used inside Verbs Event subclasses, so $this->id (the Verbs snowflake event
 * id) is available — declared via @mixin for static analysis.
 *
 * recordAudit() is dispatched from handle(), which Verbs re-runs in
 * Phase::Replay as well as Phase::Handle. The dispatched AuditableEvent carries
 * the event's snowflake id as verbEventId, so LogAction dedups on it via
 * firstOrCreate: the first fire writes the row, every subsequent replay is a
 * no-op.
 *
 * Actor context (user_id / ip / user-agent) is read from the PERSISTED
 * verb_events.metadata column — the raw array attribute on {@see VerbEvent} —
 * rather than live auth()/request(). The fire-time MetadataManager callback (in
 * AppServiceProvider) stamps that column at commit; the event row is flushed
 * before handle() runs (and is re-read on replay), so the original actor is
 * preserved across replay even in a console context with no auth. Reading the
 * raw array attribute sidesteps Verbs' Symfony Metadata deserialization, which
 * does not round-trip arbitrary custom keys back into the Metadata object.
 *
 * @mixin Event
 */
trait RecordsOpportunityAudit
{
    /**
     * Dispatch an AuditableEvent for the just-projected opportunity row.
     *
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $oldValues
     */
    protected function recordAudit(Opportunity $opportunity, string $action, ?array $newValues, ?array $oldValues = null): void
    {
        $actor = $this->auditActor();

        event(new AuditableEvent(
            model: $opportunity,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            verbEventId: $this->id,
            userId: $actor['user_id'],
            ipAddress: $actor['ip_address'],
            userAgent: $actor['user_agent'],
        ));
    }

    /**
     * Resolve the firing actor from the persisted verb_events.metadata column.
     *
     * @return array{user_id: int|null, ip_address: string|null, user_agent: string|null}
     */
    protected function auditActor(): array
    {
        /** @var array<string, mixed> $metadata */
        $metadata = VerbEvent::query()->whereKey($this->id)->value('metadata') ?? [];

        $userId = $metadata['user_id'] ?? null;
        $ipAddress = $metadata['ip_address'] ?? null;
        $userAgent = $metadata['user_agent'] ?? null;

        return [
            'user_id' => $userId !== null ? (int) $userId : null,
            'ip_address' => $ipAddress !== null ? (string) $ipAddress : null,
            'user_agent' => $userAgent !== null ? (string) $userAgent : null,
        ];
    }
}

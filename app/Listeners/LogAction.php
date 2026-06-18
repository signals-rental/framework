<?php

namespace App\Listeners;

use App\Events\AuditableEvent;
use App\Models\ActionLog;

class LogAction
{
    /**
     * Persist an audit row for the given auditable event.
     *
     * Event-sourced audit (identified by a non-null verb_event_id) carries an
     * EXPLICIT actor resolved from the firing event's persisted Verbs metadata,
     * so the recorded user survives replay even when no live auth()/request()
     * context exists; a null actor is therefore a legitimate value and must not
     * fall back to live auth(). Those rows are written with firstOrCreate keyed
     * on verb_event_id so replay re-dispatch (handle() re-runs in Phase::Replay)
     * is a no-op rather than a duplicate insert.
     *
     * Legacy non event-sourced audit (verb_event_id null) keeps the original
     * behaviour exactly: live auth()->id() plus console-guarded request context,
     * inserted with create() so no verb_event_id key is set.
     *
     * Failure handling differs by branch on purpose. The event-sourced audit
     * runs inside the firing event's handle(), which executes within the
     * commitVerbs() DB::transaction; swallowing a failed audit insert there
     * would break the all-or-nothing guarantee (and can leave a PG transaction
     * aborted), so its exceptions PROPAGATE to roll the whole commit back. The
     * legacy live-auth branch keeps the original swallow-and-report behaviour so
     * a best-effort audit can never break an otherwise-successful operation.
     */
    public function handle(AuditableEvent $event): void
    {
        if ($event->verbEventId !== null) {
            // Event-sourced branch: let ALL failures propagate so the surrounding
            // commitVerbs() transaction rolls back the event row AND the
            // projection. No try/catch here — a swallowed failure would break the
            // all-or-nothing guarantee.
            ActionLog::firstOrCreate(
                ['verb_event_id' => $event->verbEventId],
                $this->attributes($event) + [
                    'user_id' => $event->userId,
                    'ip_address' => $event->ipAddress,
                    'user_agent' => $event->userAgent,
                ],
            );

            return;
        }

        // Legacy live-auth branch: best-effort audit. The full body (including
        // attribute resolution) is guarded so a failed audit can never break an
        // otherwise-successful operation — the original behaviour, preserved.
        try {
            $request = app()->runningInConsole() ? null : request();

            ActionLog::create($this->attributes($event) + [
                'user_id' => auth()->id(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * The shared, actor-independent audit attributes for the event.
     *
     * @return array<string, mixed>
     */
    private function attributes(AuditableEvent $event): array
    {
        return [
            'action' => $event->action,
            'auditable_type' => $event->model->getMorphClass(),
            'auditable_id' => $event->model->getKey(),
            'old_values' => $event->oldValues,
            'new_values' => $event->newValues,
            'metadata' => $event->metadata,
        ];
    }
}

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
     */
    public function handle(AuditableEvent $event): void
    {
        try {
            $attributes = [
                'action' => $event->action,
                'auditable_type' => $event->model->getMorphClass(),
                'auditable_id' => $event->model->getKey(),
                'old_values' => $event->oldValues,
                'new_values' => $event->newValues,
                'metadata' => $event->metadata,
            ];

            if ($event->verbEventId !== null) {
                ActionLog::firstOrCreate(
                    ['verb_event_id' => $event->verbEventId],
                    $attributes + [
                        'user_id' => $event->userId,
                        'ip_address' => $event->ipAddress,
                        'user_agent' => $event->userAgent,
                    ],
                );

                return;
            }

            $request = app()->runningInConsole() ? null : request();

            ActionLog::create($attributes + [
                'user_id' => auth()->id(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

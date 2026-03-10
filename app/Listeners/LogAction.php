<?php

namespace App\Listeners;

use App\Events\AuditableEvent;
use App\Models\ActionLog;

class LogAction
{
    public function handle(AuditableEvent $event): void
    {
        try {
            $request = app()->runningInConsole() ? null : request();

            ActionLog::create([
                'user_id' => auth()->id(),
                'action' => $event->action,
                'auditable_type' => $event->model->getMorphClass(),
                'auditable_id' => $event->model->getKey(),
                'old_values' => $event->oldValues,
                'new_values' => $event->newValues,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'metadata' => $event->metadata,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

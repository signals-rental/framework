<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class AuditableEvent
{
    use Dispatchable;

    /**
     * The trailing actor fields let an event-sourced caller pass an EXPLICIT
     * actor (resolved from persisted Verbs metadata) instead of the live
     * auth()/request() context. They are only honoured when a $verbEventId is
     * also supplied; legacy non event-sourced callers omit all four and keep the
     * original live-auth behaviour byte-for-byte. $verbEventId additionally acts
     * as the idempotency key so replay re-dispatch inserts nothing twice.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public Model $model,
        public string $action,
        public ?array $oldValues = null,
        public ?array $newValues = null,
        public ?array $metadata = null,
        public ?int $verbEventId = null,
        public ?int $userId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}

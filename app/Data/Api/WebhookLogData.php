<?php

namespace App\Data\Api;

use App\Models\WebhookLog;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class WebhookLogData extends Data
{
    public function __construct(
        public int $id,
        public string $event,
        public ?int $response_code,
        public int $attempts,
        public ?string $delivered_at,
        public ?string $created_at,
    ) {}

    public static function fromModel(WebhookLog $log): self
    {
        /** @var Carbon|null $deliveredAt */
        $deliveredAt = $log->delivered_at;

        /** @var Carbon|null $createdAt */
        $createdAt = $log->created_at;

        return new self(
            id: $log->id,
            event: $log->event,
            response_code: $log->response_code,
            attempts: $log->attempts,
            delivered_at: $deliveredAt?->toIso8601String(),
            created_at: $createdAt?->toIso8601String(),
        );
    }
}

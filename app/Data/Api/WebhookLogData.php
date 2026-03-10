<?php

namespace App\Data\Api;

use App\Models\WebhookLog;
use Illuminate\Support\Carbon;

class WebhookLogData
{
    public function __construct(
        public readonly int $id,
        public readonly string $event,
        public readonly ?int $response_code,
        public readonly int $attempts,
        public readonly ?string $delivered_at,
        public readonly ?string $created_at,
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'response_code' => $this->response_code,
            'attempts' => $this->attempts,
            'delivered_at' => $this->delivered_at,
            'created_at' => $this->created_at,
        ];
    }
}

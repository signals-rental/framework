<?php

namespace App\Data\Api;

use App\Models\Webhook;
use Illuminate\Support\Carbon;

class WebhookData
{
    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public readonly int $id,
        public readonly string $url,
        public readonly array $events,
        public readonly bool $is_active,
        public readonly int $consecutive_failures,
        public readonly ?string $disabled_at,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {}

    public static function fromModel(Webhook $webhook): self
    {
        /** @var Carbon|null $disabledAt */
        $disabledAt = $webhook->disabled_at;

        /** @var Carbon|null $createdAt */
        $createdAt = $webhook->created_at;

        /** @var Carbon|null $updatedAt */
        $updatedAt = $webhook->updated_at;

        return new self(
            id: $webhook->id,
            url: $webhook->url,
            events: $webhook->events ?? [],
            is_active: (bool) $webhook->is_active,
            consecutive_failures: (int) $webhook->consecutive_failures,
            disabled_at: $disabledAt?->toIso8601String(),
            created_at: $createdAt?->toIso8601String(),
            updated_at: $updatedAt?->toIso8601String(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'events' => $this->events,
            'is_active' => $this->is_active,
            'consecutive_failures' => $this->consecutive_failures,
            'disabled_at' => $this->disabled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
